<?php
/**
 * Super Admin helper.
 *
 * Adds a `super_admins` table (self-healing, same pattern as the other
 * helpers) and exposes auth + CRUD functions for managing companies.
 * Super Admins are a completely separate identity from CRM `users` —
 * they are not tied to any company and do not use company_id scoping.
 */

require_once __DIR__ . '/storage_helper.php';
require_once __DIR__ . '/plan_helper.php';

function ensure_super_admins_table($conn) {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $conn->query("CREATE TABLE IF NOT EXISTS `super_admins` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `username` varchar(100) NOT NULL,
        `password` varchar(255) NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `username` (`username`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // Seed a default super admin account if none exists yet, so the
    // dashboard is reachable out of the box. Default credentials:
    // username: superadmin / password: SuperAdmin@123
    $check = $conn->query("SELECT id FROM super_admins LIMIT 1");
    if ($check && $check->num_rows === 0) {
        $default_hash = password_hash('SuperAdmin@123', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO super_admins (username, password) VALUES (?, ?)");
        $username = 'superadmin';
        $stmt->bind_param("ss", $username, $default_hash);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Verifies super admin credentials. Returns the admin row on success,
 * or null on failure.
 */
function verify_super_admin($conn, $username, $password) {
    ensure_super_admins_table($conn);
    $stmt = $conn->prepare("SELECT id, username, password FROM super_admins WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows !== 1) {
        $stmt->close();
        return null;
    }
    $admin = $result->fetch_assoc();
    $stmt->close();

    if (password_verify($password, $admin['password'])) {
        return $admin;
    }
    return null;
}

/**
 * Guards super-admin-only pages. Requires super_admin_helper.php and
 * config.php ($conn, session) to already be loaded by the caller.
 */
function require_super_admin() {
    if (!isset($_SESSION['super_admin_id'])) {
        header("Location: super_admin_login.php");
        exit();
    }
}

/**
 * Returns all companies with their subscription info and a live count
 * of users/clients, most recently created first.
 */
function list_all_companies($conn) {
    ensure_company_id_columns($conn);
    ensure_storage_limit_column($conn);
    ensure_subscription_plans_table($conn);
    $sql = "SELECT c.id, c.name, c.status, c.subscription_start, c.subscription_end, c.created_at, c.max_users, c.storage_limit_gb,
                   c.plan_id, p.name AS plan_name,
                   (SELECT COUNT(*) FROM users u WHERE u.company_id = c.id) AS user_count,
                   (SELECT COUNT(*) FROM clients cl WHERE cl.company_id = c.id) AS client_count,
                   (SELECT COALESCE(SUM(d.file_size), 0) FROM client_documents d WHERE d.company_id = c.id) AS storage_used_bytes
            FROM companies c
            LEFT JOIN subscription_plans p ON p.id = c.plan_id
            ORDER BY c.created_at DESC";
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function get_company_by_id($conn, $company_id) {
    ensure_company_id_columns($conn);
    $stmt = $conn->prepare("SELECT id, name, status, subscription_start, subscription_end, created_at FROM companies WHERE id = ?");
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->num_rows > 0 ? $result->fetch_assoc() : null;
    $stmt->close();
    return $row;
}

/**
 * Creates a new company as the Super Admin, with optional subscription
 * dates/status. Returns the new company id, or null if the name is
 * empty or already taken.
 */
function admin_create_company($conn, $name, $subscription_start = null, $subscription_end = null, $status = 'active') {
    ensure_company_id_columns($conn);
    $name = trim($name);
    if ($name === '') {
        return null;
    }

    $stmt = $conn->prepare("SELECT id FROM companies WHERE name = ?");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    if ($exists) {
        return null;
    }

    $subscription_start = $subscription_start ?: null;
    $subscription_end = $subscription_end ?: null;
    $status = in_array($status, ['active', 'suspended', 'expired'], true) ? $status : 'active';

    $stmt = $conn->prepare("INSERT INTO companies (name, subscription_start, subscription_end, status) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $name, $subscription_start, $subscription_end, $status);
    $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();
    return $id;
}

/**
 * Updates a company's name and subscription fields.
 */
function admin_update_company($conn, $company_id, $name, $subscription_start, $subscription_end, $status) {
    ensure_company_id_columns($conn);
    $name = trim($name);
    if ($name === '') {
        return false;
    }
    $subscription_start = $subscription_start ?: null;
    $subscription_end = $subscription_end ?: null;
    $status = in_array($status, ['active', 'suspended', 'expired'], true) ? $status : 'active';

    $stmt = $conn->prepare("UPDATE companies SET name = ?, subscription_start = ?, subscription_end = ?, status = ? WHERE id = ?");
    $stmt->bind_param("ssssi", $name, $subscription_start, $subscription_end, $status, $company_id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/**
 * Suspends a company (blocks login for all of its users while keeping
 * all data intact — reuses the existing subscription status check).
 */
function admin_suspend_company($conn, $company_id) {
    ensure_company_id_columns($conn);
    $stmt = $conn->prepare("UPDATE companies SET status = 'suspended' WHERE id = ?");
    $stmt->bind_param("i", $company_id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/**
 * Reactivates a suspended company.
 */
function admin_reactivate_company($conn, $company_id) {
    ensure_company_id_columns($conn);
    $stmt = $conn->prepare("UPDATE companies SET status = 'active' WHERE id = ?");
    $stmt->bind_param("i", $company_id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/**
 * Extends a company's subscription end date by a number of days from
 * whichever is later: today, or its current subscription_end. If the
 * company has no subscription_end yet, it's extended from today.
 * Also reactivates the company (status = active).
 */
function admin_extend_subscription($conn, $company_id, $days) {
    ensure_company_id_columns($conn);
    $days = (int) $days;
    if ($days <= 0) {
        return false;
    }

    $company = get_company_by_id($conn, $company_id);
    if ($company === null) {
        return false;
    }

    $today = new DateTime();
    $base = $today;
    if (!empty($company['subscription_end'])) {
        $current_end = DateTime::createFromFormat('Y-m-d', $company['subscription_end']);
        if ($current_end !== false && $current_end > $today) {
            $base = $current_end;
        }
    }
    $base->modify("+{$days} days");
    $new_end = $base->format('Y-m-d');

    $stmt = $conn->prepare("UPDATE companies SET subscription_end = ?, status = 'active' WHERE id = ?");
    $stmt->bind_param("si", $new_end, $company_id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/**
 * Permanently deletes a company and all of its associated data
 * (users, clients, and everything scoped to those clients). This is
 * destructive and irreversible — used only for genuine deletion, not
 * for suspension.
 */
function admin_delete_company($conn, $company_id) {
    ensure_company_id_columns($conn);

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("DELETE FROM client_documents WHERE company_id = ?");
        $stmt->bind_param("i", $company_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM client_reminders WHERE company_id = ?");
        $stmt->bind_param("i", $company_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM client_activity_log WHERE company_id = ?");
        $stmt->bind_param("i", $company_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM notifications WHERE company_id = ?");
        $stmt->bind_param("i", $company_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM clients WHERE company_id = ?");
        $stmt->bind_param("i", $company_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM users WHERE company_id = ?");
        $stmt->bind_param("i", $company_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM companies WHERE id = ?");
        $stmt->bind_param("i", $company_id);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        return false;
    }
}
