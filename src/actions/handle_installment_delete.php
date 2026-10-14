<?php
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../installment_helper.php';

$company_id = $_SESSION['company_id'] ?? null;
$installment_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($installment_id > 0) {
    delete_installment($conn, $installment_id, $company_id);
}

header("Location: ../../public/installment_tracker.php");
exit();
?>
