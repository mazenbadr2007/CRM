<?php
/**
 * Sales Manager permissions (backend only).
 *
 * A sales_manager can:
 *   - see own team                       -> get_team_members()
 *   - assign leads to own team           -> assign_lead_to_user()
 *   - set employee lead targets          -> set_employee_lead_target()
 *   - see own team's calls / follow-ups / visits / sales / reports
 *                                        -> get_team_report(), get_team_targets()
 *
 * "Own team" = users in the same company whose manager_id is this manager.
 * A manager can never read or write records of another team. Company admins
 * keep full company access through the same functions.
 */

require_once __DIR__ . '/access_helper.php';

function access_is_company_admin() {
    return current_user_role() === ROLE_COMPANY_ADMIN;
}

function access_is_sales_manager() {
    return current_user_role() === ROLE_SALES_MANAGER;
}

/**
 * Gate for admin + manager only endpoints.
 * $as_json = true  -> 403 JSON (action endpoints)
 * $as_json = false -> redirect (pages)
 */
function require_team_lead_access($as_json = true) {
    if (access_can_see_team()) {
        return;
    }
    if ($as_json) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Forbidden']);
    } else {
        header('Location: home.php');
    }
    exit();
}

/** Team members (excluding the manager). Admin: every non-admin user in the company. */
function get_team_members($conn) {
    $company_id = (int) ($_SESSION['company_id'] ?? 0);
    $user_id = (int) ($_SESSION['user_id'] ?? 0);
    if ($company_id <= 0) {
        return [];
    }

    if (access_is_company_admin()) {
        $admin_role = ROLE_COMPANY_ADMIN;
        $stmt = $conn->prepare("SELECT id, username, role FROM users WHERE company_id = ? AND role <> ? ORDER BY username ASC");
        $stmt->bind_param("is", $company_id, $admin_role);
    } elseif (access_is_sales_manager()) {
        $stmt = $conn->prepare("SELECT id, username, role FROM users WHERE company_id = ? AND manager_id = ? ORDER BY username ASC");
        $stmt->bind_param("ii", $company_id, $user_id);
    } else {
        return [];
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function get_team_member_ids($conn) {
    return array_map('intval', array_column(get_team_members($conn), 'id'));
}

function is_in_my_team($conn, $target_user_id) {
    return in_array((int) $target_user_id, get_team_member_ids($conn), true);
}

/* ------------------------------------------------------------------ */
/* Assign leads                                                        */
/* ------------------------------------------------------------------ */

/** Manager: self or own team. Admin: any user of the company. */
function can_assign_lead_to($conn, $assignee_id) {
    $assignee_id = (int) $assignee_id;
    $company_id = (int) ($_SESSION['company_id'] ?? 0);
    if ($assignee_id <= 0 || $company_id <= 0) {
        return false;
    }

    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND company_id = ?");
    $stmt->bind_param("ii", $assignee_id, $company_id);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    if (!$exists) {
        return false;
    }

    if (access_is_company_admin()) {
        return true;
    }
    if (access_is_sales_manager()) {
        return $assignee_id === (int) $_SESSION['user_id'] || is_in_my_team($conn, $assignee_id);
    }
    return false;
}

/**
 * Reassign a lead/client. The lead must already be inside the caller's scope
 * (fetch_accessible_client) and the assignee inside the caller's team.
 * @return array ['ok' => bool, 'error' => string|null]
 */
function assign_lead_to_user($conn, $client_id, $assignee_id) {
    $client_id = (int) $client_id;
    $assignee_id = (int) $assignee_id;
    $company_id = (int) ($_SESSION['company_id'] ?? 0);

    if (!access_can_see_team()) {
        return ['ok' => false, 'error' => 'forbidden'];
    }

    $client = fetch_accessible_client($conn, $client_id, false);
    if ($client === null) {
        return ['ok' => false, 'error' => 'client_not_found'];
    }
    if (!can_assign_lead_to($conn, $assignee_id)) {
        return ['ok' => false, 'error' => 'assignee_not_allowed'];
    }
    if ((int) $client['user_id'] === $assignee_id) {
        return ['ok' => true, 'error' => null];
    }

    // Besides the checks above, the database refuses this UPDATE on its own if it
    // breaks the actor's team scope (trg_clients_team_scope_guard, access_helper.php).
    $stmt = $conn->prepare("UPDATE clients SET user_id = ? WHERE id = ? AND company_id = ?");
    $stmt->bind_param("iii", $assignee_id, $client_id, $company_id);
    try {
        $done = $stmt->execute();
    } catch (Throwable $e) {
        $done = false;
    }
    $stmt->close();
    if (!$done) {
        return ['ok' => false, 'error' => 'db_error'];
    }

    require_once __DIR__ . '/activity_logger.php';
    require_once __DIR__ . '/notification_helper.php';

    $actor_id = (int) $_SESSION['user_id'];
    $actor_name = $_SESSION['username'] ?? 'Unknown';

    $name_stmt = $conn->prepare("SELECT username FROM users WHERE id = ? AND company_id = ?");
    $name_stmt->bind_param("ii", $assignee_id, $company_id);
    $name_stmt->execute();
    $assignee_name = $name_stmt->get_result()->fetch_assoc()['username'] ?? 'user';
    $name_stmt->close();

    log_activity($conn, $client_id, $actor_id, $actor_name, 'lead_assigned', "Assigned this lead to {$assignee_name}.");

    if ($assignee_id !== $actor_id) {
        $client_name = $client['client_name'] ?? 'Client';
        create_notification(
            $conn, $assignee_id, $client_id, 'new_lead', 'Lead Assigned',
            "\"{$client_name}\" was assigned to you by {$actor_name}."
        );
    }
    return ['ok' => true, 'error' => null];
}

/* ------------------------------------------------------------------ */
/* Employee lead targets                                               */
/* ------------------------------------------------------------------ */

function ensure_lead_targets_table($conn) {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;
    $conn->query(
        "CREATE TABLE IF NOT EXISTS `employee_lead_targets` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `company_id` INT(11) NOT NULL,
            `user_id` INT(11) NOT NULL,
            `period_month` CHAR(7) NOT NULL,
            `target_leads` INT(11) NOT NULL DEFAULT 0,
            `set_by` INT(11) NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_target` (`company_id`, `user_id`, `period_month`),
            KEY `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

function is_valid_target_period($period) {
    return (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $period);
}

/** Manager: own team members only. Admin: any non-admin company user. */
function set_employee_lead_target($conn, $employee_id, $period, $target_leads) {
    $employee_id = (int) $employee_id;
    $target_leads = (int) $target_leads;
    $company_id = (int) ($_SESSION['company_id'] ?? 0);
    $set_by = (int) ($_SESSION['user_id'] ?? 0);

    if (!access_can_see_team()) {
        return ['ok' => false, 'error' => 'forbidden'];
    }
    if (!is_valid_target_period($period)) {
        return ['ok' => false, 'error' => 'invalid_period'];
    }
    if ($target_leads < 0 || $target_leads > 100000) {
        return ['ok' => false, 'error' => 'invalid_target'];
    }
    if (!is_in_my_team($conn, $employee_id)) {
        return ['ok' => false, 'error' => 'employee_not_in_team'];
    }

    ensure_lead_targets_table($conn);
    $stmt = $conn->prepare(
        "INSERT INTO employee_lead_targets (company_id, user_id, period_month, target_leads, set_by)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE target_leads = VALUES(target_leads), set_by = VALUES(set_by)"
    );
    $stmt->bind_param("iisii", $company_id, $employee_id, $period, $target_leads, $set_by);
    $done = $stmt->execute();
    $stmt->close();
    return $done ? ['ok' => true, 'error' => null] : ['ok' => false, 'error' => 'db_error'];
}

/** Targets vs actual new leads for each team member in a month (YYYY-MM). */
function get_team_targets($conn, $period = null) {
    $period = $period ?: date('Y-m');
    if (!access_can_see_team() || !is_valid_target_period($period)) {
        return [];
    }
    $members = get_team_members($conn);
    if (!$members) {
        return [];
    }
    ensure_lead_targets_table($conn);

    $company_id = (int) $_SESSION['company_id'];
    $from = $period . '-01 00:00:00';
    $to = date('Y-m-d 00:00:00', strtotime($period . '-01 +1 month'));

    $out = [];
    foreach ($members as $m) {
        $uid = (int) $m['id'];

        $t = $conn->prepare("SELECT target_leads FROM employee_lead_targets WHERE company_id = ? AND user_id = ? AND period_month = ?");
        $t->bind_param("iis", $company_id, $uid, $period);
        $t->execute();
        $target = (int) ($t->get_result()->fetch_assoc()['target_leads'] ?? 0);
        $t->close();

        $a = $conn->prepare("SELECT COUNT(*) AS n FROM clients WHERE company_id = ? AND user_id = ? AND created_at >= ? AND created_at < ?");
        $a->bind_param("iiss", $company_id, $uid, $from, $to);
        $a->execute();
        $actual = (int) $a->get_result()->fetch_assoc()['n'];
        $a->close();

        $out[] = [
            'user_id' => $uid,
            'username' => $m['username'],
            'period' => $period,
            'target_leads' => $target,
            'actual_leads' => $actual,
        ];
    }
    return $out;
}

/* ------------------------------------------------------------------ */
/* Team reports                                                        */
/* ------------------------------------------------------------------ */

/**
 * Per-person calls / follow-ups / visits / sales for the caller's scope
 * (manager: self + own team, admin: company). Optional created_at range.
 */
function get_team_report($conn, $date_from = null, $date_to = null) {
    if (!access_can_see_team()) {
        return [];
    }
    require_once __DIR__ . '/pipeline_helper.php';
    ensure_pipeline_stage_column($conn);

    $company_id = (int) ($_SESSION['company_id'] ?? 0);
    $join_extra = '';
    $types = 'i';
    $params = [$company_id];

    if ($date_from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
        $join_extra .= " AND c.created_at >= ?";
        $types .= 's';
        $params[] = $date_from . ' 00:00:00';
    }
    if ($date_to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
        $join_extra .= " AND c.created_at < DATE_ADD(?, INTERVAL 1 DAY)";
        $types .= 's';
        $params[] = $date_to . ' 00:00:00';
    }
    // Placeholder order in the SQL: c.company_id, [dates], u.company_id
    $types .= 'i';
    $params[] = $company_id;

    $scope = access_user_ids_sql($conn, 'u.id');
    $sql = "SELECT u.id, u.username, u.role,
                COUNT(c.id) AS leads,
                COALESCE(SUM(CASE WHEN c.call_outcome IS NOT NULL AND c.call_outcome <> '' THEN 1 ELSE 0 END), 0) AS calls,
                COALESCE(SUM(CASE WHEN c.event_date IS NOT NULL THEN 1 ELSE 0 END), 0) AS follow_ups,
                COALESCE(SUM(CASE WHEN c.visit_outcome IS NOT NULL AND c.visit_outcome <> '' THEN 1 ELSE 0 END), 0) AS visits,
                COALESCE(SUM(CASE WHEN c.pipeline_stage = 'Won' THEN 1 ELSE 0 END), 0) AS sales
            FROM users u
            LEFT JOIN clients c ON c.user_id = u.id AND c.company_id = ?" . $join_extra . "
            WHERE u.company_id = ?" . $scope . "
            GROUP BY u.id, u.username, u.role
            ORDER BY u.username ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}
