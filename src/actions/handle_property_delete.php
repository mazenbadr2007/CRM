<?php
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../property_helper.php';

$company_id = $_SESSION['company_id'] ?? null;
$property_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($property_id > 0) {
    delete_property($conn, $property_id, $company_id);
}

header("Location: ../../public/property_inventory.php");
exit();
?>
