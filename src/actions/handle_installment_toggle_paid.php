<?php
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../installment_helper.php';

header('Content-Type: application/json');

function installment_json_response($ok, $data = []) {
    echo json_encode(array_merge(['success' => $ok], $data));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    installment_json_response(false, ['error' => 'Method not allowed']);
}

$company_id = $_SESSION['company_id'] ?? null;
$installment_id = isset($_POST['installment_id']) ? (int) $_POST['installment_id'] : 0;
$is_paid = isset($_POST['is_paid']) && $_POST['is_paid'] === '1';

if ($installment_id <= 0) {
    http_response_code(400);
    installment_json_response(false, ['error' => 'Invalid request']);
}

$row = get_installment($conn, $installment_id, $company_id);
if (!$row) {
    http_response_code(404);
    installment_json_response(false, ['error' => 'Installment not found']);
}

$ok = set_installment_paid($conn, $installment_id, $company_id, $is_paid);

if ($ok) {
    $refreshed = get_installment($conn, $installment_id, $company_id);
    installment_json_response(true, ['status' => compute_installment_status($refreshed)]);
} else {
    http_response_code(500);
    installment_json_response(false, ['error' => 'Database error: ' . $conn->error]);
}
