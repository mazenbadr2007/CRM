<?php
/**
 * Company Users (team members) helper.
 *
 * Manages the CRM users that belong to a company (the manager account
 * plus any agents/employees). Mirrors the pattern used by
 * developer_helper.php: every read/write is scoped by company_id, and
 * the `users`/`email` columns themselves are kept self-healing via
 * ensure_company_id_columns() in tenant_helper.php.
 */

require_once __DIR__ . '/tenant_helper.php';
require_once __DIR__ . '/access_helper.php';

/**
 * All users belonging to a company, most recently created first.
 */
function get_company_users($conn, $company_id) {
    ensure_company_id_columns($conn);
    $stmt = $conn->prepare(
        "SELECT id, username, email, company_id, role, manager_id, created_at
         FROM users
         WHERE company_id = ?
         ORDER BY created_at DESC"
    );
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/**
 * A single user, only if it belongs to the given company.
 */
function get_company_user($conn, $user_id, $company_id) {
    ensure_company_id_columns($conn);
    $stmt = $conn->prepare(
        "SELECT id, username, email, company_id, role, manager_id, created_at
         FROM users
         WHERE id = ? AND company_id = ?"
    );
    $stmt->bind_param("ii", $user_id, $company_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->num_rows > 0 ? $result->fetch_assoc() : null;
    $stmt->close();
    return $row;
}

/**
 * Creates a new user (e.g. an agent/employee) under a company.
 * Returns the new user id on success, or null if the company's user
 * limit is already reached.
 */
function create_company_user($conn, $company_id, $username, $email, $password, $role = ROLE_SALES_EMPLOYEE, $manager_id = null) {
    ensure_company_id_columns($conn);
    ensure_user_role_columns($conn);

    if (!company_can_add_user($conn, $company_id)) {
        return null;
    }

    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
    $role = normalize_user_role($role);
    $manager_id = ($role === ROLE_SALES_EMPLOYEE && $manager_id) ? (int) $manager_id : 0;

    $stmt = $conn->prepare(
        "INSERT INTO users (username, password, company_id, email, role, manager_id) VALUES (?, ?, ?, ?, ?, NULLIF(?, 0))"
    );
    $stmt->bind_param("ssissi", $username, $hashed_password, $company_id, $email, $role, $manager_id);
    $ok = $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();
    return $ok ? $id : null;
}

/**
 * Updates an existing user's username/email, and optionally their
 * password (only when a new one is supplied). Scoped to company_id so
 * a manager can never edit another company's user.
 */
function update_company_user($conn, $user_id, $company_id, $username, $email, $password = null, $role = null, $manager_id = null) {
    ensure_company_id_columns($conn);
    ensure_user_role_columns($conn);

    $role = $role !== null ? normalize_user_role($role) : ROLE_SALES_EMPLOYEE;
    $manager_id = ($role === ROLE_SALES_EMPLOYEE && $manager_id) ? (int) $manager_id : 0;

    if ($password !== null && $password !== '') {
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare(
            "UPDATE users SET username = ?, email = ?, password = ?, role = ?, manager_id = NULLIF(?, 0) WHERE id = ? AND company_id = ?"
        );
        $stmt->bind_param("ssssiii", $username, $email, $hashed_password, $role, $manager_id, $user_id, $company_id);
    } else {
        $stmt = $conn->prepare(
            "UPDATE users SET username = ?, email = ?, role = ?, manager_id = NULLIF(?, 0) WHERE id = ? AND company_id = ?"
        );
        $stmt->bind_param("sssiii", $username, $email, $role, $manager_id, $user_id, $company_id);
    }
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}
