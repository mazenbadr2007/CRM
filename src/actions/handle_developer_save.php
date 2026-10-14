<?php
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../developer_helper.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../../public/developers.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$company_id = $_SESSION['company_id'] ?? null;

$data = [
    'name'  => trim($_POST['name'] ?? ''),
    'phone' => trim($_POST['phone'] ?? ''),
    'email' => trim($_POST['email'] ?? ''),
    'notes' => trim($_POST['notes'] ?? ''),
];

if ($data['name'] === '') {
    header("Location: ../../public/developers.php?error=missing_fields");
    exit();
}

$developer_id = isset($_POST['developer_id']) ? (int) $_POST['developer_id'] : 0;

if ($developer_id > 0) {
    update_developer($conn, $developer_id, $company_id, $data);
} else {
    create_developer($conn, $company_id, $user_id, $data);
}

header("Location: ../../public/developers.php");
exit();
?>
