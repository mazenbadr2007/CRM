<?php
/**
 * Role-based access for company users.
 *
 * company_admin  — all company data
 * sales_manager  — own records plus assigned team members
 * sales_employee — only assigned (own) leads/clients
 */

if (!defined('ROLE_COMPANY_ADMIN')) {
    define('ROLE_COMPANY_ADMIN', 'company_admin');
    define('ROLE_SALES_MANAGER', 'sales_manager');
    define('ROLE_SALES_EMPLOYEE', 'sales_employee');
}

function ensure_user_role_columns($conn) {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    if (!function_exists('ensure_company_id_columns')) {
        require_once __DIR__ . '/tenant_helper.php';
    }
    ensure_company_id_columns($conn);

    $role_check = $conn->query("SHOW COLUMNS FROM `users` LIKE 'role'");
    $role_is_new = $role_check && $role_check->num_rows === 0;
    _ensure_column($conn, 'users', 'role', "VARCHAR(30) NOT NULL DEFAULT 'sales_employee'");
    _ensure_column($conn, 'users', 'manager_id', 'INT(11) DEFAULT NULL');
    // Round-robin state for Smart Lead Distribution (see lead_distribution_helper.php).
    _ensure_column($conn, 'users', 'last_lead_assigned_at', 'DATETIME(6) DEFAULT NULL');

    $idx = $conn->query("SHOW INDEX FROM `users` WHERE Key_name = 'manager_id'");
    if ($idx && $idx->num_rows === 0) {
        $conn->query("ALTER TABLE `users` ADD KEY `manager_id` (`manager_id`)");
    }

    if ($role_is_new) {
        $conn->query("UPDATE users SET role = '" . ROLE_COMPANY_ADMIN . "' WHERE LOWER(username) = 'managercrm'");
        $conn->query(
            "UPDATE users u
             INNER JOIN (
                SELECT company_id, MIN(id) AS first_id
                FROM users
                WHERE company_id IS NOT NULL
                GROUP BY company_id
             ) first_users ON u.id = first_users.first_id
             SET u.role = '" . ROLE_COMPANY_ADMIN . "'"
        );
    }

    ensure_client_reassign_guard($conn);
    ensure_lead_assignment_guards($conn);
}

/**
 * Company-admin-only gate for backend actions (settings, API keys, backups).
 * Employees and managers get a 403 and are sent home.
 */
function require_company_admin() {
    if (current_user_role() === ROLE_COMPANY_ADMIN) {
        return;
    }
    // Plain 302 redirect. (A 403 status together with a Location header is not
    // followed by browsers and makes Apache show its own "Forbidden" page.)
    $base = defined('BASE_URL') ? BASE_URL : '';
    header('Location: ' . $base . '/home.php');
    exit();
}

/**
 * Database-level guard: a lead can only be re-owned by a user of the same
 * company, no matter which code path (or manual SQL) performs the UPDATE.
 */
function ensure_client_reassign_guard($conn) {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;
    try {
        $res = $conn->query(
            "SELECT 1 FROM information_schema.TRIGGERS
             WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = 'trg_clients_reassign_guard' LIMIT 1"
        );
        if ($res && $res->num_rows === 0) {
            $conn->query(
                "CREATE TRIGGER trg_clients_reassign_guard BEFORE UPDATE ON clients
                 FOR EACH ROW
                 BEGIN
                    IF NEW.user_id <> OLD.user_id THEN
                        IF NOT EXISTS (SELECT 1 FROM users WHERE id = NEW.user_id AND company_id = OLD.company_id) THEN
                            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Lead can only be assigned to a user of the same company';
                        END IF;
                    END IF;
                 END"
            );
        }
    } catch (Throwable $e) {
        // Missing TRIGGER privilege or unsupported server: app-level checks still apply.
    }
}

/**
 * Database-level lead-assignment guards (Smart Lead Distribution).
 *
 *  trg_clients_insert_owner_guard  - a new lead can only be created for a user
 *                                    of the SAME company (user_id 0 = legacy API sentinel).
 *  trg_clients_team_scope_guard    - when the application has declared who is acting
 *                                    (db_set_actor_context), the database itself refuses:
 *                                      * employees changing a lead's owner at all
 *                                      * managers moving leads out of / into another team
 *                                    Manual SQL without an actor context is not restricted
 *                                    beyond the same-company guard above.
 */
function ensure_lead_assignment_guards($conn) {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;
    try {
        $have = [];
        $res = $conn->query(
            "SELECT TRIGGER_NAME FROM information_schema.TRIGGERS
             WHERE TRIGGER_SCHEMA = DATABASE()
               AND TRIGGER_NAME IN ('trg_clients_insert_owner_guard', 'trg_clients_team_scope_guard')"
        );
        while ($res && ($row = $res->fetch_assoc())) {
            $have[$row['TRIGGER_NAME']] = true;
        }

        if (empty($have['trg_clients_insert_owner_guard'])) {
            $conn->query(
                "CREATE TRIGGER trg_clients_insert_owner_guard BEFORE INSERT ON clients
                 FOR EACH ROW
                 BEGIN
                    IF NEW.company_id IS NOT NULL AND NEW.user_id <> 0 THEN
                        IF NOT EXISTS (SELECT 1 FROM users WHERE id = NEW.user_id AND company_id = NEW.company_id) THEN
                            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Lead owner must belong to the same company';
                        END IF;
                    END IF;
                 END"
            );
        }

        if (empty($have['trg_clients_team_scope_guard'])) {
            $conn->query(
                "CREATE TRIGGER trg_clients_team_scope_guard BEFORE UPDATE ON clients
                 FOR EACH ROW
                 BEGIN
                    IF NEW.user_id <> OLD.user_id AND @crm_actor_role IS NOT NULL THEN
                        IF @crm_actor_role = 'sales_employee' THEN
                            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Employees cannot reassign leads';
                        END IF;
                        IF @crm_actor_role = 'sales_manager' THEN
                            IF OLD.user_id <> @crm_actor_id AND NOT EXISTS (
                                SELECT 1 FROM users WHERE id = OLD.user_id AND manager_id = @crm_actor_id AND company_id = OLD.company_id
                            ) THEN
                                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Lead is outside your team';
                            END IF;
                            IF NEW.user_id <> @crm_actor_id AND NOT EXISTS (
                                SELECT 1 FROM users WHERE id = NEW.user_id AND manager_id = @crm_actor_id AND company_id = OLD.company_id
                            ) THEN
                                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Leads can only be assigned inside your team';
                            END IF;
                        END IF;
                    END IF;
                 END"
            );
        }
    } catch (Throwable $e) {
        // Missing TRIGGER privilege or unsupported server: the application-level checks still apply.
    }
}

/**
 * Tells the database who is acting on this connection so the guards above
 * apply to every write made by this request. Called for each authenticated request.
 */
function db_set_actor_context($conn, $user_id, $role) {
    $user_id = (int) $user_id;
    $role = normalize_user_role($role);          // whitelisted value, safe to inline
    try {
        $conn->query("SET @crm_actor_id = {$user_id}, @crm_actor_role = '{$role}'");
    } catch (Throwable $e) {
    }
}

function normalize_user_role($role) {
    $role = strtolower(trim((string) $role));
    if ($role === ROLE_COMPANY_ADMIN || $role === ROLE_SALES_MANAGER || $role === ROLE_SALES_EMPLOYEE) {
        return $role;
    }
    return ROLE_SALES_EMPLOYEE;
}

function hydrate_session_access($conn, $user = null) {
    ensure_user_role_columns($conn);

    $user_id = (int) ($user['id'] ?? ($_SESSION['user_id'] ?? 0));
    if ($user_id <= 0) {
        return;
    }

    if ($user === null || !isset($user['role'])) {
        $stmt = $conn->prepare("SELECT id, role, manager_id, username FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    if (!$user) {
        return;
    }

    $role = normalize_user_role($user['role'] ?? '');
    if ($role === ROLE_SALES_EMPLOYEE && strtolower((string) ($user['username'] ?? '')) === 'managercrm') {
        $role = ROLE_COMPANY_ADMIN;
        $upd = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        $upd->bind_param("si", $role, $user_id);
        $upd->execute();
        $upd->close();
    }

    $_SESSION['user_role'] = $role;
    $_SESSION['manager_id'] = isset($user['manager_id']) && $user['manager_id'] !== null ? (int) $user['manager_id'] : null;
    $_SESSION['is_manager'] = ($role === ROLE_COMPANY_ADMIN);

    // From here on the database enforces lead-assignment scope for this request's writes.
    db_set_actor_context($conn, $user_id, $role);
}

function current_user_role() {
    return normalize_user_role($_SESSION['user_role'] ?? ROLE_SALES_EMPLOYEE);
}

function access_can_see_team() {
    $role = current_user_role();
    return $role === ROLE_COMPANY_ADMIN || $role === ROLE_SALES_MANAGER;
}

/**
 * Accessible user ids for lead/client ownership.
 * null = entire company (company admin).
 */
function get_accessible_user_ids($conn) {
    ensure_user_role_columns($conn);

    $role = current_user_role();
    $user_id = (int) ($_SESSION['user_id'] ?? 0);
    $company_id = (int) ($_SESSION['company_id'] ?? 0);

    if ($role === ROLE_COMPANY_ADMIN) {
        return null;
    }

    $ids = [$user_id];
    if ($role === ROLE_SALES_MANAGER && $company_id > 0 && $user_id > 0) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE company_id = ? AND manager_id = ?");
        $stmt->bind_param("ii", $company_id, $user_id);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        foreach ($rows as $row) {
            $ids[] = (int) $row['id'];
        }
    }

    return array_values(array_unique(array_map('intval', $ids)));
}

function access_user_filter($conn, $column = 'user_id') {
    $ids = get_accessible_user_ids($conn);
    if ($ids === null) {
        return ['sql' => '', 'types' => '', 'params' => []];
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    return [
        'sql' => " AND {$column} IN ({$placeholders})",
        'types' => str_repeat('i', count($ids)),
        'params' => $ids,
    ];
}

function access_user_ids_sql($conn, $column = 'user_id') {
    $ids = get_accessible_user_ids($conn);
    if ($ids === null) {
        return '';
    }
    $safe = implode(',', array_map('intval', $ids));
    return " AND {$column} IN ({$safe})";
}

function fetch_accessible_client($conn, $client_id, $with_employee_name = false) {
    $client_id = (int) $client_id;
    $company_id = (int) ($_SESSION['company_id'] ?? 0);
    $filter = access_user_filter($conn, 'c.user_id');

    if ($with_employee_name) {
        $sql = "SELECT c.*, u.username AS employee_name
                FROM clients c
                JOIN users u ON c.user_id = u.id
                WHERE c.id = ? AND c.company_id = ?" . $filter['sql'];
    } else {
        $sql = "SELECT c.* FROM clients c WHERE c.id = ? AND c.company_id = ?" . $filter['sql'];
    }

    $stmt = $conn->prepare($sql);
    $types = 'ii' . $filter['types'];
    $params = array_merge([$client_id, $company_id], $filter['params']);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function user_can_access_client($conn, $client_id) {
    return fetch_accessible_client($conn, $client_id, false) !== null;
}

function list_accessible_clients($conn, $with_employee_name = false) {
    $company_id = (int) ($_SESSION['company_id'] ?? 0);

    if ($with_employee_name) {
        $filter = access_user_filter($conn, 'c.user_id');
        $sql = "SELECT c.*, u.username AS employee_name
                FROM clients c
                JOIN users u ON c.user_id = u.id
                WHERE c.company_id = ?" . $filter['sql'] . "
                ORDER BY c.created_at DESC";
    } else {
        $filter = access_user_filter($conn, 'user_id');
        $sql = "SELECT * FROM clients WHERE company_id = ?" . $filter['sql'] . " ORDER BY created_at DESC";
    }

    $stmt = $conn->prepare($sql);
    $types = 'i' . $filter['types'];
    $params = array_merge([$company_id], $filter['params']);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function get_company_sales_managers($conn, $company_id) {
    ensure_user_role_columns($conn);
    $stmt = $conn->prepare(
        "SELECT id, username FROM users WHERE company_id = ? AND role = ? ORDER BY username ASC"
    );
    $role = ROLE_SALES_MANAGER;
    $stmt->bind_param("is", $company_id, $role);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}
