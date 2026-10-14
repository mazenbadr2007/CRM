<?php
/**
 * Smart Lead Distribution.
 *
 * Every NEW lead is given an owner automatically:
 *
 *   1. Candidate pool (who may receive the lead)
 *        company_admin / API key -> every Sales Employee of the company
 *        sales_manager           -> the Sales Employees of that manager's own team
 *        sales_employee          -> the employee keeps the lead they registered
 *                                   (set LEAD_AUTO_ASSIGN_EMPLOYEE_CREATED to true to
 *                                   distribute those among the employee's teammates too)
 *   2. Balance   : the candidate with the FEWEST open leads wins
 *                  (open = pipeline stage other than Won / Lost).
 *   3. Round robin: candidates tied on open leads are rotated by
 *                  `users.last_lead_assigned_at` (never-assigned first, then the
 *                  longest-waiting), so equal loads alternate fairly.
 *
 * Concurrency: assign_new_lead_owner() OPENS a READ COMMITTED transaction,
 * locks the candidate rows (SELECT ... FOR UPDATE) and stamps the rotation.
 * The caller then INSERTs the lead and calls commit() (or rollback()), so two
 * leads created at the same moment are processed one after the other and the
 * second one already sees the first one's lead in the counts.
 *
 * Manual re-assignment by a manager stays in manager_helper.php
 * (assign_lead_to_user); the database triggers created in access_helper.php
 * enforce the team scope for it as well.
 */

require_once __DIR__ . '/access_helper.php';
require_once __DIR__ . '/pipeline_helper.php';

if (!defined('LEAD_AUTO_ASSIGN_EMPLOYEE_CREATED')) {
    define('LEAD_AUTO_ASSIGN_EMPLOYEE_CREATED', false);
}

/**
 * Ids of the employees that may receive a new lead created by this actor.
 * $creator_id / $creator_role are null for leads created through the API.
 */
function lead_distribution_pool($conn, $company_id, $creator_id, $creator_role) {
    $company_id = (int) $company_id;
    $creator_id = (int) $creator_id;
    $employee = ROLE_SALES_EMPLOYEE;

    if ($creator_role === ROLE_SALES_MANAGER) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE company_id = ? AND role = ? AND manager_id = ? ORDER BY id");
        $stmt->bind_param("isi", $company_id, $employee, $creator_id);
    } elseif ($creator_role === ROLE_SALES_EMPLOYEE) {
        if (!LEAD_AUTO_ASSIGN_EMPLOYEE_CREATED) {
            return [];
        }
        $me = $conn->prepare("SELECT manager_id FROM users WHERE id = ? AND company_id = ?");
        $me->bind_param("ii", $creator_id, $company_id);
        $me->execute();
        $manager_id = $me->get_result()->fetch_assoc()['manager_id'] ?? null;
        $me->close();
        if ($manager_id !== null) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE company_id = ? AND role = ? AND manager_id = ? ORDER BY id");
            $mid = (int) $manager_id;
            $stmt->bind_param("isi", $company_id, $employee, $mid);
        } else {
            $stmt = $conn->prepare("SELECT id FROM users WHERE company_id = ? AND role = ? AND manager_id IS NULL ORDER BY id");
            $stmt->bind_param("is", $company_id, $employee);
        }
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE company_id = ? AND role = ? ORDER BY id");
        $stmt->bind_param("is", $company_id, $employee);
    }

    $stmt->execute();
    $ids = array_map('intval', array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id'));
    $stmt->close();
    return $ids;
}

/**
 * Picks the candidate with the fewest open leads (ties: round robin) and
 * stamps the rotation. Runs inside the transaction opened by assign_new_lead_owner().
 * @return int|null user id, or null when the pool is empty
 */
function lead_distribution_pick($conn, $company_id, array $pool_ids) {
    $pool_ids = array_values(array_unique(array_map('intval', $pool_ids)));
    if (!$pool_ids) {
        return null;
    }
    $company_id = (int) $company_id;
    $in = implode(',', $pool_ids);

    // Serialize concurrent distributions that touch the same people.
    $conn->query("SELECT id FROM users WHERE company_id = {$company_id} AND id IN ({$in}) FOR UPDATE");

    $sql = "SELECT u.id, COUNT(c.id) AS open_leads
            FROM users u
            LEFT JOIN clients c
                   ON c.user_id = u.id
                  AND c.company_id = u.company_id
                  AND COALESCE(NULLIF(c.pipeline_stage, ''), 'New Lead') NOT IN ('Won', 'Lost')
            WHERE u.company_id = {$company_id} AND u.id IN ({$in})
            GROUP BY u.id, u.last_lead_assigned_at
            ORDER BY open_leads ASC,
                     (u.last_lead_assigned_at IS NULL) DESC,
                     u.last_lead_assigned_at ASC,
                     u.id ASC
            LIMIT 1";
    $res = $conn->query($sql);
    $row = $res ? $res->fetch_assoc() : null;
    if (!$row) {
        return null;
    }

    $winner = (int) $row['id'];
    $stamp = $conn->prepare("UPDATE users SET last_lead_assigned_at = NOW(6) WHERE id = ? AND company_id = ?");
    $stamp->bind_param("ii", $winner, $company_id);
    $stamp->execute();
    $stamp->close();
    return $winner;
}

/**
 * Decides the owner of a brand-new lead and leaves a transaction OPEN:
 * INSERT the lead with the returned user_id, then $conn->commit()
 * (or $conn->rollback() if the insert fails).
 *
 * @return array ['user_id' => int, 'auto' => bool, 'mode' => string]
 *         mode: round_robin | creator | fallback
 */
function assign_new_lead_owner($conn, $company_id, $creator_id = null, $creator_role = null) {
    // Schema work (DDL) must happen before the transaction starts: DDL commits implicitly.
    ensure_user_role_columns($conn);   // rotation column + database guards
    ensure_pipeline_stage_column($conn);
    $creator_id = $creator_id !== null ? (int) $creator_id : null;

    // READ COMMITTED for this transaction only: after waiting for the row lock the
    // counts must reflect leads committed by the request that held it.
    $conn->query("SET TRANSACTION ISOLATION LEVEL READ COMMITTED");
    $conn->begin_transaction();

    $pool = lead_distribution_pool($conn, $company_id, $creator_id, $creator_role);
    $picked = $pool ? lead_distribution_pick($conn, $company_id, $pool) : null;
    if ($picked !== null) {
        return ['user_id' => $picked, 'auto' => true, 'mode' => 'round_robin'];
    }

    // Nobody to distribute to (or an employee keeping their own lead).
    if ($creator_id !== null && $creator_id > 0) {
        return ['user_id' => $creator_id, 'auto' => false, 'mode' => 'creator'];
    }

    // API lead and no employees yet: hand it to the company admin so it is never orphaned.
    $admin = ROLE_COMPANY_ADMIN;
    $stmt = $conn->prepare("SELECT id FROM users WHERE company_id = ? AND role = ? ORDER BY id ASC LIMIT 1");
    $cid = (int) $company_id;
    $stmt->bind_param("is", $cid, $admin);
    $stmt->execute();
    $admin_id = (int) ($stmt->get_result()->fetch_assoc()['id'] ?? 0);
    $stmt->close();
    return ['user_id' => $admin_id, 'auto' => false, 'mode' => 'fallback'];
}

/**
 * Timeline entry + notification for the person who received the lead.
 * Works for web requests (session company) and for the API (no session):
 * the company is passed explicitly and applied to the session only for the calls.
 */
function announce_lead_assignment($conn, $company_id, $client_id, $client_name, $assignee_id, $actor_id, $actor_name, $mode = 'round_robin') {
    if ((int) $assignee_id <= 0 || (int) $assignee_id === (int) $actor_id) {
        return;
    }
    require_once __DIR__ . '/activity_logger.php';
    require_once __DIR__ . '/notification_helper.php';

    $previous_company = $_SESSION['company_id'] ?? null;
    $_SESSION['company_id'] = (int) $company_id;
    try {
        $stmt = $conn->prepare("SELECT username FROM users WHERE id = ? AND company_id = ?");
        $aid = (int) $assignee_id;
        $cid = (int) $company_id;
        $stmt->bind_param("ii", $aid, $cid);
        $stmt->execute();
        $assignee_name = $stmt->get_result()->fetch_assoc()['username'] ?? 'user';
        $stmt->close();

        $how = $mode === 'round_robin' ? 'automatically (round robin, balanced by open leads)' : 'automatically';
        log_activity($conn, (int) $client_id, (int) $actor_id, $actor_name, 'lead_assigned', "Lead assigned {$how} to {$assignee_name}.");
        create_notification(
            $conn, $aid, (int) $client_id, 'new_lead', 'Lead Assigned',
            "\"{$client_name}\" was assigned to you automatically."
        );
    } finally {
        if ($previous_company === null) {
            unset($_SESSION['company_id']);
        } else {
            $_SESSION['company_id'] = $previous_company;
        }
    }
}
