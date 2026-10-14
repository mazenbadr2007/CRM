<?php
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../property_helper.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../../public/property_inventory.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$company_id = $_SESSION['company_id'] ?? null;

$data = [
    'developer'     => trim($_POST['developer'] ?? ''),
    'project'       => trim($_POST['project'] ?? ''),
    'unit_type'     => trim($_POST['unit_type'] ?? ''),
    'area'          => $_POST['area'] !== '' ? (float) $_POST['area'] : null,
    'bedrooms'      => $_POST['bedrooms'] !== '' ? (int) $_POST['bedrooms'] : null,
    'price'         => $_POST['price'] !== '' ? (float) $_POST['price'] : null,
    'down_payment'  => $_POST['down_payment'] !== '' ? (float) $_POST['down_payment'] : null,
    'installments'  => trim($_POST['installments'] ?? ''),
    'status'        => trim($_POST['status'] ?? 'available'),
    'company_commission_type'  => in_array(($_POST['company_commission_type'] ?? 'percent'), ['percent', 'fixed'], true) ? $_POST['company_commission_type'] : 'percent',
    'company_commission_value' => ($_POST['company_commission_value'] ?? '') !== '' ? (float) $_POST['company_commission_value'] : null,
    'sales_commission_type'    => in_array(($_POST['sales_commission_type'] ?? 'percent'), ['percent', 'fixed'], true) ? $_POST['sales_commission_type'] : 'percent',
    'sales_commission_value'   => ($_POST['sales_commission_value'] ?? '') !== '' ? (float) $_POST['sales_commission_value'] : null,
];

if ($data['developer'] === '' || $data['project'] === '' || $data['unit_type'] === '') {
    header("Location: ../../public/property_inventory.php?error=missing_fields");
    exit();
}

$property_id = isset($_POST['property_id']) ? (int) $_POST['property_id'] : 0;

if ($property_id > 0) {
    update_property($conn, $property_id, $company_id, $data);
} else {
    create_property($conn, $company_id, $user_id, $data);
}

header("Location: ../../public/property_inventory.php");
exit();
?>
