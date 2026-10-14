<?php
/**
 * Multi-Tenant (Company) helper.
 *
 * Adds a `companies` table and a `company_id` column to every relevant
 * table. Self-healing (CREATE TABLE IF NOT EXISTS / ADD COLUMN IF NOT
 * EXISTS), just like the other helpers.
 *
 * All filtering for cross-company isolation is done through the helpers
 * exposed by this file.
 */

function ensure_companies_table($conn) {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $sql = "CREATE TABLE IF NOT EXISTS `companies` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(150) NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    $conn->query($sql);
}

function _ensure_column($conn, $table, $column, $definition) {
    $check = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
    if ($check && $check->num_rows === 0) {
        $conn->query("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    }
}

function ensure_company_id_columns($conn) {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    ensure_companies_table($conn);

    _ensure_column($conn, 'users', 'company_id', 'int(11) DEFAULT NULL');
    _ensure_column($conn, 'users', 'email', 'varchar(150) DEFAULT NULL');
    _ensure_column($conn, 'clients', 'company_id', 'int(11) DEFAULT NULL');

    $conn->query("CREATE TABLE IF NOT EXISTS `client_activity_log` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `client_id` int(11) NOT NULL,
        `user_id` int(11) DEFAULT NULL,
        `employee_name` varchar(100) DEFAULT NULL,
        `action_type` varchar(50) NOT NULL,
        `description` text DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `client_id` (`client_id`),
        KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    _ensure_column($conn, 'client_activity_log', 'company_id', 'int(11) DEFAULT NULL');

    $conn->query("CREATE TABLE IF NOT EXISTS `client_documents` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `client_id` int(11) NOT NULL,
        `user_id` int(11) DEFAULT NULL,
        `category` varchar(20) NOT NULL,
        `original_name` varchar(255) NOT NULL,
        `stored_name` varchar(255) NOT NULL,
        `file_size` int(11) DEFAULT NULL,
        `mime_type` varchar(100) DEFAULT NULL,
        `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `client_id` (`client_id`),
        KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    _ensure_column($conn, 'client_documents', 'company_id', 'int(11) DEFAULT NULL');

    $conn->query("CREATE TABLE IF NOT EXISTS `client_reminders` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `client_id` int(11) NOT NULL,
        `user_id` int(11) DEFAULT NULL,
        `reminder_date` date DEFAULT NULL,
        `reminder_time` time DEFAULT NULL,
        `status` varchar(20) NOT NULL DEFAULT 'active',
        `note` text DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `resolved_at` timestamp NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `client_id` (`client_id`),
        KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    _ensure_column($conn, 'client_reminders', 'company_id', 'int(11) DEFAULT NULL');

    $conn->query("CREATE TABLE IF NOT EXISTS `notifications` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `client_id` int(11) DEFAULT NULL,
        `type` varchar(30) NOT NULL,
        `title` varchar(150) NOT NULL,
        `message` text DEFAULT NULL,
        `ref_date` date DEFAULT NULL,
        `is_read` tinyint(1) NOT NULL DEFAULT 0,
        `is_done` tinyint(1) NOT NULL DEFAULT 0,
        `snoozed_until` datetime DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`),
        KEY `client_id` (`client_id`),
        KEY `type` (`type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    _ensure_column($conn, 'notifications', 'company_id', 'int(11) DEFAULT NULL');

    _ensure_column($conn, 'clients', 'pipeline_stage', "VARCHAR(50) NOT NULL DEFAULT 'New Lead'");

    // Lead Source Management: tracks where each client/lead originated from
    // (Facebook, Instagram, TikTok, Website, WhatsApp, Referral). Self-healing
    // like every other column here, see src/lead_source_helper.php for the
    // canonical list of sources and related helpers.
    _ensure_column($conn, 'clients', 'lead_source', "VARCHAR(50) NOT NULL DEFAULT 'Website'");

    _ensure_column($conn, 'companies', 'subscription_start', 'DATE DEFAULT NULL');
    _ensure_column($conn, 'companies', 'subscription_end', 'DATE DEFAULT NULL');
    _ensure_column($conn, 'companies', 'status', "VARCHAR(20) NOT NULL DEFAULT 'active'");

    // User Limits: maximum number of users allowed for this company's
    // plan. NULL means unlimited (the default, so existing companies
    // are unaffected until a Super Admin sets a limit).
    _ensure_column($conn, 'companies', 'max_users', 'INT(11) DEFAULT NULL');

    // Multi-Tenant usernames: usernames only need to be unique within
    // the same company, not globally, so two different companies can
    // both have a "manager" account. Drops the old global UNIQUE on
    // `username` (if present) and replaces it with a composite UNIQUE
    // on (company_id, username). Self-healing like everything else
    // here — safe to call repeatedly.
    $existing_index = $conn->query("SHOW INDEX FROM `users` WHERE Key_name = 'username'");
    if ($existing_index && $existing_index->num_rows > 0) {
        $conn->query("ALTER TABLE `users` DROP INDEX `username`");
    }
    $composite_index = $conn->query("SHOW INDEX FROM `users` WHERE Key_name = 'company_id_username'");
    if ($composite_index && $composite_index->num_rows === 0) {
        $conn->query("ALTER TABLE `users` ADD UNIQUE KEY `company_id_username` (`company_id`, `username`)");
    }

    // Multi-Tenant emails: like usernames, emails only need to be
    // unique within the same company, not globally, so two different
    // companies can each have a user with the same email address.
    $composite_email_index = $conn->query("SHOW INDEX FROM `users` WHERE Key_name = 'company_id_email'");
    if ($composite_email_index && $composite_email_index->num_rows === 0) {
        $conn->query("ALTER TABLE `users` ADD UNIQUE KEY `company_id_email` (`company_id`, `email`)");
    }
}

/**
 * Whether a username is already taken within a specific company.
 * Usernames are scoped per company (company_id, username), so the
 * same username may exist under different companies.
 */
function username_exists_in_company($conn, $company_id, $username) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE company_id = ? AND username = ?");
    $stmt->bind_param("is", $company_id, $username);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $exists;
}

/**
 * Finds a user by (company_id, username) — used by login to
 * authenticate within a specific company instead of matching
 * username globally.
 */
function find_user_by_company_and_username($conn, $company_id, $username) {
    if (file_exists(__DIR__ . '/access_helper.php')) {
        require_once __DIR__ . '/access_helper.php';
        ensure_user_role_columns($conn);
    }
    $stmt = $conn->prepare("SELECT id, username, password, company_id, email, role, manager_id FROM users WHERE company_id = ? AND username = ?");
    $stmt->bind_param("is", $company_id, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->num_rows === 1 ? $result->fetch_assoc() : null;
    $stmt->close();
    return $row;
}

/**
 * Whether an email is already taken within a specific company.
 * Emails are scoped per company (company_id, email), so the same
 * email may exist under different companies. Optionally excludes a
 * given user id, so a user can save their own unchanged email.
 */
function email_exists_in_company($conn, $company_id, $email, $exclude_user_id = null) {
    if ($exclude_user_id !== null) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE company_id = ? AND email = ? AND id != ?");
        $stmt->bind_param("isi", $company_id, $email, $exclude_user_id);
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE company_id = ? AND email = ?");
        $stmt->bind_param("is", $company_id, $email);
    }
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $exists;
}

/**
 * Looks up a company's id by its exact name. Returns null if no such
 * company exists.
 */
function get_company_id_by_name($conn, $company_name) {
    ensure_companies_table($conn);
    $company_name = trim($company_name);
    if ($company_name === '') {
        return null;
    }
    $stmt = $conn->prepare("SELECT id FROM companies WHERE name = ?");
    $stmt->bind_param("s", $company_name);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->num_rows > 0 ? $result->fetch_assoc() : null;
    $stmt->close();
    return $row === null ? null : (int) $row['id'];
}

/**
 * Returns the subscription row (start, end, status) for a company, or
 * null if the company doesn't exist.
 */
function get_company_subscription($conn, $company_id) {
    ensure_company_id_columns($conn);
    $stmt = $conn->prepare("SELECT subscription_start, subscription_end, status FROM companies WHERE id = ?");
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->num_rows > 0 ? $result->fetch_assoc() : null;
    $stmt->close();
    return $row;
}

/**
 * Determines whether a company's subscription is currently active.
 *
 * A company is considered expired if its status is explicitly not
 * 'active', or if it has a subscription_end date that has passed.
 * Companies with no subscription_end set (e.g. legacy/free companies)
 * are treated as active as long as status isn't 'expired'/'suspended'.
 */
function is_subscription_active($conn, $company_id) {
    $sub = get_company_subscription($conn, $company_id);
    if ($sub === null) {
        return true;
    }

    $status = strtolower($sub['status'] ?? 'active');
    if ($status !== 'active') {
        return false;
    }

    if (!empty($sub['subscription_end'])) {
        $end = DateTime::createFromFormat('Y-m-d', $sub['subscription_end']);
        if ($end !== false) {
            $end->setTime(23, 59, 59);
            if ($end < new DateTime()) {
                return false;
            }
        }
    }

    return true;
}

function get_current_company_id() {
    return $_SESSION['company_id'] ?? null;
}

function tenant_where_clause($table_alias = null) {
    $company_id = get_current_company_id();
    if ($company_id === null) {
        return ' 1=1 ';
    }
    $prefix = $table_alias ? "`{$table_alias}`." : '';
    return " {$prefix}company_id = " . (int)$company_id . " ";
}

function create_company($conn, $name) {
    ensure_companies_table($conn);
    $name = trim($name);
    if ($name === '') {
        return null;
    }
    $stmt = $conn->prepare("SELECT id FROM companies WHERE name = ?");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return (int)$row['id'];
    }
    $stmt->close();

    $insert = $conn->prepare("INSERT INTO companies (name) VALUES (?)");
    $insert->bind_param("s", $name);
    $insert->execute();
    $id = $insert->insert_id;
    $insert->close();
    return $id;
}

function assign_user_to_company($conn, $user_id, $company_id) {
    $stmt = $conn->prepare("UPDATE users SET company_id = ? WHERE id = ?");
    $stmt->bind_param("ii", $company_id, $user_id);
    $stmt->execute();
    $stmt->close();
}

function get_user_company_id($conn, $user_id) {
    ensure_company_id_columns($conn);
    $stmt = $conn->prepare("SELECT company_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['company_id'] === null ? null : (int)$row['company_id'];
    }
    $stmt->close();
    return null;
}

/**
 * User Limits.
 *
 * Each company's subscription plan can have a maximum number of
 * users (companies.max_users). NULL means unlimited. The Super Admin
 * sets/changes this per company; regular users never see or edit it.
 */

/**
 * Returns the company's user limit, or null if unlimited.
 */
function get_company_user_limit($conn, $company_id) {
    ensure_company_id_columns($conn);
    $stmt = $conn->prepare("SELECT max_users FROM companies WHERE id = ?");
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->num_rows > 0 ? $result->fetch_assoc() : null;
    $stmt->close();
    if ($row === null || $row['max_users'] === null) {
        return null;
    }
    return (int) $row['max_users'];
}

/**
 * Current number of users belonging to a company.
 */
function count_company_users($conn, $company_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM users WHERE company_id = ?");
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $total = (int) $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    return $total;
}

/**
 * Whether a company still has room for at least one more user under
 * its plan's limit. A null limit (unlimited) always returns true.
 */
function company_can_add_user($conn, $company_id) {
    $limit = get_company_user_limit($conn, $company_id);
    if ($limit === null) {
        return true;
    }
    return count_company_users($conn, $company_id) < $limit;
}

/**
 * The professional, user-facing message shown when a company's user
 * limit has been reached.
 */
function user_limit_reached_message() {
    return "Your plan has reached the maximum number of users. Please upgrade your subscription.";
}

/**
 * Sets (or clears, with null) a company's user limit. Called only
 * from the Super Admin dashboard.
 */
function admin_set_company_user_limit($conn, $company_id, $max_users) {
    ensure_company_id_columns($conn);
    $company_id = (int) $company_id;
    $max_users = ($max_users === null || $max_users === '') ? null : (int) $max_users;

    if ($max_users === null) {
        $stmt = $conn->prepare("UPDATE companies SET max_users = NULL WHERE id = ?");
        $stmt->bind_param("i", $company_id);
    } else {
        $stmt = $conn->prepare("UPDATE companies SET max_users = ? WHERE id = ?");
        $stmt->bind_param("ii", $max_users, $company_id);
    }
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}
