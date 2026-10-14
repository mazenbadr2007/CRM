<?php
/**
 * Subscription Plans helper.
 *
 * Adds a `subscription_plans` table and a `plan_id` column on
 * `companies` (self-healing, same pattern as every other helper in
 * this codebase). Lets the Super Admin define reusable plans
 * (name, monthly/yearly price, max users, storage limit, status) and
 * assign one to each company.
 *
 * Assigning a plan — or editing a plan that companies are already on —
 * automatically pushes the plan's `max_users` / `storage_limit_gb`
 * onto every affected company by reusing the existing limit helpers
 * in tenant_helper.php / storage_helper.php, so enforcement continues
 * to happen exactly where it already does today.
 */

require_once __DIR__ . '/tenant_helper.php';
require_once __DIR__ . '/storage_helper.php';

function ensure_subscription_plans_table($conn) {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $conn->query("CREATE TABLE IF NOT EXISTS `subscription_plans` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(100) NOT NULL,
        `monthly_price` decimal(10,2) NOT NULL DEFAULT 0.00,
        `yearly_price` decimal(10,2) NOT NULL DEFAULT 0.00,
        `max_users` int(11) DEFAULT NULL,
        `storage_limit_gb` decimal(10,2) DEFAULT NULL,
        `status` varchar(20) NOT NULL DEFAULT 'active',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    ensure_company_id_columns($conn);
    _ensure_column($conn, 'companies', 'plan_id', 'INT(11) DEFAULT NULL');
}

/**
 * Returns every plan, most recently created first.
 */
function list_all_plans($conn) {
    ensure_subscription_plans_table($conn);
    $sql = "SELECT p.*, (SELECT COUNT(*) FROM companies c WHERE c.plan_id = p.id) AS company_count
            FROM subscription_plans p
            ORDER BY p.created_at DESC";
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

/**
 * Only the active plans — used to populate the "Assign Plan" dropdown
 * so companies can't be put on a retired plan.
 */
function list_active_plans($conn) {
    ensure_subscription_plans_table($conn);
    $sql = "SELECT * FROM subscription_plans WHERE status = 'active' ORDER BY monthly_price ASC, name ASC";
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function get_plan_by_id($conn, $plan_id) {
    ensure_subscription_plans_table($conn);
    $stmt = $conn->prepare("SELECT * FROM subscription_plans WHERE id = ?");
    $stmt->bind_param("i", $plan_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->num_rows > 0 ? $result->fetch_assoc() : null;
    $stmt->close();
    return $row;
}

/**
 * Creates a new plan. Returns the new plan id, or null if the name is
 * empty or already taken.
 */
function admin_create_plan($conn, $name, $monthly_price, $yearly_price, $max_users, $storage_limit_gb, $status = 'active') {
    ensure_subscription_plans_table($conn);
    $name = trim($name);
    if ($name === '') {
        return null;
    }

    $stmt = $conn->prepare("SELECT id FROM subscription_plans WHERE name = ?");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    if ($exists) {
        return null;
    }

    $monthly_price = (float) $monthly_price;
    $yearly_price = (float) $yearly_price;
    $max_users = ($max_users === null || $max_users === '') ? null : (int) $max_users;
    $storage_limit_gb = ($storage_limit_gb === null || $storage_limit_gb === '') ? null : (float) $storage_limit_gb;
    $status = in_array($status, ['active', 'inactive'], true) ? $status : 'active';

    $stmt = $conn->prepare("INSERT INTO subscription_plans (name, monthly_price, yearly_price, max_users, storage_limit_gb, status) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sddids", $name, $monthly_price, $yearly_price, $max_users, $storage_limit_gb, $status);
    $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();
    return $id;
}

/**
 * Updates a plan's details, then re-applies its (possibly changed)
 * limits to every company currently assigned to it — this is how a
 * limit change on the plan automatically propagates without the
 * Super Admin having to touch each company individually.
 */
function admin_update_plan($conn, $plan_id, $name, $monthly_price, $yearly_price, $max_users, $storage_limit_gb, $status) {
    ensure_subscription_plans_table($conn);
    $plan_id = (int) $plan_id;
    $name = trim($name);
    if ($name === '') {
        return false;
    }

    $monthly_price = (float) $monthly_price;
    $yearly_price = (float) $yearly_price;
    $max_users = ($max_users === null || $max_users === '') ? null : (int) $max_users;
    $storage_limit_gb = ($storage_limit_gb === null || $storage_limit_gb === '') ? null : (float) $storage_limit_gb;
    $status = in_array($status, ['active', 'inactive'], true) ? $status : 'active';

    $stmt = $conn->prepare("UPDATE subscription_plans SET name = ?, monthly_price = ?, yearly_price = ?, max_users = ?, storage_limit_gb = ?, status = ? WHERE id = ?");
    $stmt->bind_param("sddidsi", $name, $monthly_price, $yearly_price, $max_users, $storage_limit_gb, $status, $plan_id);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        apply_plan_limits_to_companies($conn, $plan_id);
    }

    return $ok;
}

/**
 * Toggles a plan between active / inactive. Inactive plans stay
 * assigned to whatever companies already have them (their limits
 * keep applying), but can no longer be picked for new assignments.
 */
function admin_set_plan_status($conn, $plan_id, $status) {
    ensure_subscription_plans_table($conn);
    $plan_id = (int) $plan_id;
    $status = in_array($status, ['active', 'inactive'], true) ? $status : 'active';

    $stmt = $conn->prepare("UPDATE subscription_plans SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $plan_id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/**
 * Deletes a plan. Companies that were on it simply fall back to
 * unassigned (plan_id = NULL) and keep whatever limits they already
 * had — deleting a plan never deletes a company or its data.
 */
function admin_delete_plan($conn, $plan_id) {
    ensure_subscription_plans_table($conn);
    $plan_id = (int) $plan_id;

    $stmt = $conn->prepare("UPDATE companies SET plan_id = NULL WHERE plan_id = ?");
    $stmt->bind_param("i", $plan_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM subscription_plans WHERE id = ?");
    $stmt->bind_param("i", $plan_id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/**
 * Assigns a plan to a company and immediately applies its limits
 * (max_users / storage_limit_gb) to that company. Passing null clears
 * the assignment, leaving the company's own limits untouched.
 */
function admin_assign_plan_to_company($conn, $company_id, $plan_id) {
    ensure_subscription_plans_table($conn);
    $company_id = (int) $company_id;
    $plan_id = ($plan_id === null || $plan_id === '') ? null : (int) $plan_id;

    if ($plan_id === null) {
        $stmt = $conn->prepare("UPDATE companies SET plan_id = NULL WHERE id = ?");
        $stmt->bind_param("i", $company_id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    $plan = get_plan_by_id($conn, $plan_id);
    if ($plan === null) {
        return false;
    }

    $stmt = $conn->prepare("UPDATE companies SET plan_id = ? WHERE id = ?");
    $stmt->bind_param("ii", $plan_id, $company_id);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        admin_set_company_user_limit($conn, $company_id, $plan['max_users']);
        admin_set_company_storage_limit($conn, $company_id, $plan['storage_limit_gb']);
    }

    return $ok;
}

/**
 * Re-applies a plan's current max_users / storage_limit_gb to every
 * company assigned to it. Called automatically after admin_update_plan()
 * so limit changes take effect for existing subscribers right away.
 */
function apply_plan_limits_to_companies($conn, $plan_id) {
    $plan = get_plan_by_id($conn, $plan_id);
    if ($plan === null) {
        return;
    }

    $stmt = $conn->prepare("SELECT id FROM companies WHERE plan_id = ?");
    $stmt->bind_param("i", $plan_id);
    $stmt->execute();
    $company_ids = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id');
    $stmt->close();

    foreach ($company_ids as $company_id) {
        admin_set_company_user_limit($conn, (int) $company_id, $plan['max_users']);
        admin_set_company_storage_limit($conn, (int) $company_id, $plan['storage_limit_gb']);
    }
}

/**
 * Returns the plan row assigned to a company, or null if it has none.
 */
function get_company_plan($conn, $company_id) {
    ensure_subscription_plans_table($conn);
    $stmt = $conn->prepare("SELECT p.* FROM companies c JOIN subscription_plans p ON p.id = c.plan_id WHERE c.id = ?");
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->num_rows > 0 ? $result->fetch_assoc() : null;
    $stmt->close();
    return $row;
}
