<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../super_admin_helper.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        header("Location: ../../public/super_admin_login.php?error=empty_fields");
        exit();
    }

    $admin = verify_super_admin($conn, $username, $password);

    if ($admin) {
        session_regenerate_id(true);
        $_SESSION['super_admin_id'] = $admin['id'];
        $_SESSION['super_admin_username'] = $admin['username'];
        header("Location: ../../public/super_admin_dashboard.php");
        exit();
    }

    header("Location: ../../public/super_admin_login.php?error=invalid_credentials");
    exit();
}
?>
