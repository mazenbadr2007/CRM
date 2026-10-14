<?php
require_once __DIR__ . '/../auth/manager_check.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../user_helper.php';
require_once __DIR__ . '/../access_helper.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../../public/users_management.php");
    exit();
}

$company_id = $_SESSION['company_id'] ?? null;

$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
$role = normalize_user_role($_POST['role'] ?? ROLE_SALES_EMPLOYEE);
$manager_id = isset($_POST['manager_id']) ? (int) $_POST['manager_id'] : 0;
// Lock-out guard: an admin cannot change their own role from this form.
if ($user_id > 0 && $user_id === (int) ($_SESSION['user_id'] ?? 0)) {
    $role = current_user_role();
}
if ($role !== ROLE_SALES_EMPLOYEE) {
    $manager_id = 0;
} elseif ($manager_id > 0) {
    $mgr = get_company_user($conn, $manager_id, $company_id);
    if ($mgr === null || normalize_user_role($mgr['role'] ?? '') !== ROLE_SALES_MANAGER) {
        $manager_id = 0;
    }
}

if ($username === '' || $email === '') {
    header("Location: ../../public/users_management.php?error=empty_fields");
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: ../../public/users_management.php?error=invalid_email");
    exit();
}

// Usernames and emails are each unique per company (not globally), so
// check only within the current manager's own company, excluding the
// user currently being edited (so saving their own unchanged values
// doesn't trigger a false "taken" error).
$exclude_id = $user_id > 0 ? $user_id : null;
if (username_exists_in_company_excluding($conn, $company_id, $username, $exclude_id)) {
    header("Location: ../../public/users_management.php?error=username_taken");
    exit();
}
if (email_exists_in_company($conn, $company_id, $email, $exclude_id)) {
    header("Location: ../../public/users_management.php?error=email_taken");
    exit();
}

if ($user_id > 0) {
    // Editing an existing user: password fields are optional (leave
    // blank to keep the current password), but if provided they must
    // match.
    if ($password !== '' || $confirm_password !== '') {
        if ($password !== $confirm_password) {
            header("Location: ../../public/users_management.php?error=password_mismatch");
            exit();
        }
    }

    $existing = get_company_user($conn, $user_id, $company_id);
    if ($existing === null) {
        header("Location: ../../public/users_management.php?error=not_found");
        exit();
    }

    update_company_user($conn, $user_id, $company_id, $username, $email, $password !== '' ? $password : null, $role, $manager_id ?: null);
} else {
    // Creating a new user: password is required.
    if ($password === '' || $confirm_password === '') {
        header("Location: ../../public/users_management.php?error=empty_fields");
        exit();
    }
    if ($password !== $confirm_password) {
        header("Location: ../../public/users_management.php?error=password_mismatch");
        exit();
    }

    if (!company_can_add_user($conn, $company_id)) {
        header("Location: ../../public/users_management.php?error=user_limit_reached");
        exit();
    }

    create_company_user($conn, $company_id, $username, $email, $password, $role, $manager_id ?: null);
}

header("Location: ../../public/users_management.php");
exit();

/**
 * Local helper mirroring username_exists_in_company() but with the
 * ability to exclude the user currently being edited.
 */
function username_exists_in_company_excluding($conn, $company_id, $username, $exclude_user_id = null) {
    if ($exclude_user_id !== null) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE company_id = ? AND username = ? AND id != ?");
        $stmt->bind_param("isi", $company_id, $username, $exclude_user_id);
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE company_id = ? AND username = ?");
        $stmt->bind_param("is", $company_id, $username);
    }
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $exists;
}
?>
