<?php
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../developer_helper.php';

$company_id = $_SESSION['company_id'] ?? null;
$developer_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($developer_id > 0) {
    delete_developer($conn, $developer_id, $company_id);
}

header("Location: ../../public/developers.php");
exit();
?>
