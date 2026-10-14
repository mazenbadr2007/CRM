<?php
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../installment_helper.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../../public/installment_tracker.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$company_id = $_SESSION['company_id'] ?? null;

$data = [
    'property_id' => isset($_POST['property_id']) ? (int) $_POST['property_id'] : 0,
    'due_date'    => trim($_POST['due_date'] ?? ''),
    'amount'      => $_POST['amount'] !== '' ? (float) $_POST['amount'] : 0,
    'note'        => trim($_POST['note'] ?? ''),
];

if ($data['property_id'] <= 0 || $data['due_date'] === '' || $data['amount'] <= 0) {
    header("Location: ../../public/installment_tracker.php?error=missing_fields");
    exit();
}

$installment_id = isset($_POST['installment_id']) ? (int) $_POST['installment_id'] : 0;

if ($installment_id > 0) {
    update_installment($conn, $installment_id, $company_id, $data);
} else {
    create_installment($conn, $company_id, $user_id, $data);
}

header("Location: ../../public/installment_tracker.php");
exit();
?>
