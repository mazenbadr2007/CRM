<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../super_admin_helper.php';
require_once __DIR__ . '/../plan_helper.php';
require_super_admin();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../../public/super_admin_dashboard.php");
    exit();
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'create':
        admin_create_plan(
            $conn,
            $_POST['name'] ?? '',
            $_POST['monthly_price'] ?? 0,
            $_POST['yearly_price'] ?? 0,
            $_POST['max_users'] ?? null,
            $_POST['storage_limit_gb'] ?? null,
            $_POST['status'] ?? 'active'
        );
        break;

    case 'update':
        $plan_id = isset($_POST['plan_id']) ? (int) $_POST['plan_id'] : 0;
        if ($plan_id > 0) {
            admin_update_plan(
                $conn,
                $plan_id,
                $_POST['name'] ?? '',
                $_POST['monthly_price'] ?? 0,
                $_POST['yearly_price'] ?? 0,
                $_POST['max_users'] ?? null,
                $_POST['storage_limit_gb'] ?? null,
                $_POST['status'] ?? 'active'
            );
        }
        break;

    case 'toggle_status':
        $plan_id = isset($_POST['plan_id']) ? (int) $_POST['plan_id'] : 0;
        if ($plan_id > 0) {
            $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
            admin_set_plan_status($conn, $plan_id, $status);
        }
        break;

    case 'delete':
        $plan_id = isset($_POST['plan_id']) ? (int) $_POST['plan_id'] : 0;
        if ($plan_id > 0) {
            admin_delete_plan($conn, $plan_id);
        }
        break;

    case 'assign':
        $company_id = isset($_POST['company_id']) ? (int) $_POST['company_id'] : 0;
        if ($company_id > 0) {
            $plan_id = isset($_POST['plan_id']) ? trim($_POST['plan_id']) : '';
            admin_assign_plan_to_company($conn, $company_id, $plan_id === '' ? null : (int) $plan_id);
        }
        break;
}

header("Location: ../../public/super_admin_dashboard.php");
exit();
?>
