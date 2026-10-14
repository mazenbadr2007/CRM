<?php
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/login.php");
    exit();
}

if (!isset($_SESSION['company_id']) || $_SESSION['company_id'] === null) {
    require_once __DIR__ . '/../tenant_helper.php';
    $company_id = get_user_company_id($conn, $_SESSION['user_id']);
    if ($company_id === null) {
        $username = $_SESSION['username'] ?? '';
        if (strtolower($username) === 'managercrm') {
            $company_name = 'Head Office';
        } else {
            $company_name = 'Company_' . $_SESSION['user_id'];
        }
        $company_id = create_company($conn, $company_name);
        assign_user_to_company($conn, $_SESSION['user_id'], $company_id);
    }
    $_SESSION['company_id'] = $company_id;
}

if (!is_subscription_active($conn, $_SESSION['company_id'])) {
    session_unset();
    session_destroy();
    header("Location: " . BASE_URL . "/subscription_expired.php");
    exit();
}

require_once __DIR__ . '/../access_helper.php';
hydrate_session_access($conn);
