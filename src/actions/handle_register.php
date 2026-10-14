<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../tenant_helper.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = trim($_POST['username']);
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $company_name = isset($_POST['company_name']) ? trim($_POST['company_name']) : '';

    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        header("Location: ../../public/register.php?error=empty_fields");
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: ../../public/register.php?error=invalid_email");
        exit();
    }

    if ($password !== $confirm_password) {
        header("Location: ../../public/register.php?error=password_mismatch");
        exit();
    }

    // Resolve the target company first: joining an existing company
    // (by matching company_name) or creating a brand-new one. Every
    // registration is tied to exactly one company, and the manager
    // account created below is always scoped to that company_id.
    $joining_existing_company = false;
    if ($company_name !== '') {
        $existing_company_id = get_company_id_by_name($conn, $company_name);
        $joining_existing_company = $existing_company_id !== null;
    } else {
        $existing_company_id = null;
    }

    // Enforce the existing company's user limit before creating
    // anything. A blank/new company name always creates a brand-new
    // company, which has no limit yet, so it's never blocked here.
    if ($joining_existing_company && !company_can_add_user($conn, $existing_company_id)) {
        header("Location: ../../public/register.php?error=user_limit_reached");
        exit();
    }

    // Usernames are unique per company (company_id, username), not
    // globally, so check only within the target company.
    if ($joining_existing_company && username_exists_in_company($conn, $existing_company_id, $username)) {
        header("Location: ../../public/register.php?error=username_taken");
        exit();
    }

    // Emails are likewise unique per company (company_id, email), not
    // globally, so check only within the target company.
    if ($joining_existing_company && email_exists_in_company($conn, $existing_company_id, $email)) {
        header("Location: ../../public/register.php?error=email_taken");
        exit();
    }

    // Create the company first (if needed) so we have a company_id to
    // scope the new manager account to.
    if ($joining_existing_company) {
        $company_id = $existing_company_id;
    } else {
        $placeholder_name = $company_name !== '' ? $company_name : ('Company_' . uniqid());
        $company_id = create_company($conn, $placeholder_name);
        if ($company_id === null) {
            header("Location: ../../public/register.php?error=username_taken");
            exit();
        }
    }

    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

    // Create the CRM Manager account linked to that company_id. The
    // composite UNIQUE (company_id, username) in the users table is
    // the source of truth for uniqueness, so a duplicate insert here
    // (e.g. a race condition) fails safely instead of corrupting data.
    require_once __DIR__ . '/../access_helper.php';
    ensure_user_role_columns($conn);
    $role = $joining_existing_company ? ROLE_SALES_EMPLOYEE : ROLE_COMPANY_ADMIN;

    $insert_stmt = $conn->prepare("INSERT INTO users (username, password, company_id, email, role) VALUES (?, ?, ?, ?, ?)");
    if (!$insert_stmt) {
        die("Error in Insert statement: " . $conn->error);
    }
    $insert_stmt->bind_param("ssiss", $username, $hashed_password, $company_id, $email, $role);

    if ($insert_stmt->execute()) {
        $new_user_id = $insert_stmt->insert_id;
        $insert_stmt->close();

        // A brand-new company with no explicit name got a placeholder
        // above; rename it now that we have the new user's id, to
        // preserve the original "Company_<id>" naming behavior.
        if (!$joining_existing_company && $company_name === '') {
            $final_name = 'Company_' . $new_user_id;
            $rename_stmt = $conn->prepare("UPDATE companies SET name = ? WHERE id = ?");
            $rename_stmt->bind_param("si", $final_name, $company_id);
            $rename_stmt->execute();
            $rename_stmt->close();
        }

        header("Location: ../../public/login.php?status=registration_success");
        exit();
    } else {
        $insert_stmt->close();
        // Composite UNIQUE (company_id, username) or (company_id, email)
        // violation — extremely unlikely given the pre-checks above,
        // but handled safely.
        header("Location: ../../public/register.php?error=username_taken");
        exit();
    }
}
?>
