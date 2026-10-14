<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../tenant_helper.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $company_name = isset($_POST['company_name']) ? trim($_POST['company_name']) : '';

    if (empty($username) || empty($password) || empty($company_name)) {
        header("Location: ../../public/login.php?error=empty_fields");
        exit();
    }

    // Resolve the company first, then authenticate within it —
    // usernames are only unique per (company_id, username), so the
    // same username can exist under different companies.
    $company_id = get_company_id_by_name($conn, $company_name);
    if ($company_id === null) {
        header("Location: ../../public/login.php?error=invalid_credentials");
        exit();
    }

    $user = find_user_by_company_and_username($conn, $company_id, $username);

    if ($user !== null && password_verify($password, $user['password'])) {
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];

        if (!is_subscription_active($conn, $company_id)) {
            session_unset();
            session_destroy();
            header("Location: ../../public/subscription_expired.php");
            exit();
        }

        $_SESSION['company_id'] = $company_id;

        require_once __DIR__ . '/../access_helper.php';
        hydrate_session_access($conn, $user);

        header("Location: ../../public/home.php");
        exit();
    }

    header("Location: ../../public/login.php?error=invalid_credentials");
    exit();
}
?>
