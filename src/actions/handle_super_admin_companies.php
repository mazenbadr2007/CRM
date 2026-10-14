<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../super_admin_helper.php';
require_super_admin();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../../public/super_admin_dashboard.php");
    exit();
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'create':
        admin_create_company(
            $conn,
            $_POST['name'] ?? '',
            $_POST['subscription_start'] ?? null,
            $_POST['subscription_end'] ?? null,
            $_POST['status'] ?? 'active'
        );
        break;

    case 'update':
        $company_id = isset($_POST['company_id']) ? (int) $_POST['company_id'] : 0;
        if ($company_id > 0) {
            admin_update_company(
                $conn,
                $company_id,
                $_POST['name'] ?? '',
                $_POST['subscription_start'] ?? null,
                $_POST['subscription_end'] ?? null,
                $_POST['status'] ?? 'active'
            );
        }
        break;

    case 'suspend':
        $company_id = isset($_POST['company_id']) ? (int) $_POST['company_id'] : 0;
        if ($company_id > 0) {
            admin_suspend_company($conn, $company_id);
        }
        break;

    case 'reactivate':
        $company_id = isset($_POST['company_id']) ? (int) $_POST['company_id'] : 0;
        if ($company_id > 0) {
            admin_reactivate_company($conn, $company_id);
        }
        break;

    case 'extend':
        $company_id = isset($_POST['company_id']) ? (int) $_POST['company_id'] : 0;
        $days = isset($_POST['days']) ? (int) $_POST['days'] : 0;
        if ($company_id > 0 && $days > 0) {
            admin_extend_subscription($conn, $company_id, $days);
        }
        break;

    case 'set_user_limit':
        $company_id = isset($_POST['company_id']) ? (int) $_POST['company_id'] : 0;
        if ($company_id > 0) {
            $max_users = isset($_POST['max_users']) ? trim($_POST['max_users']) : '';
            admin_set_company_user_limit($conn, $company_id, $max_users === '' ? null : (int) $max_users);
        }
        break;

    case 'set_storage_limit':
        $company_id = isset($_POST['company_id']) ? (int) $_POST['company_id'] : 0;
        if ($company_id > 0) {
            $storage_limit_gb = isset($_POST['storage_limit_gb']) ? trim($_POST['storage_limit_gb']) : '';
            admin_set_company_storage_limit($conn, $company_id, $storage_limit_gb === '' ? null : (float) $storage_limit_gb);
        }
        break;

    case 'delete':
        $company_id = isset($_POST['company_id']) ? (int) $_POST['company_id'] : 0;
        if ($company_id > 0) {
            admin_delete_company($conn, $company_id);
        }
        break;
}

header("Location: ../../public/super_admin_dashboard.php");
exit();
?>
