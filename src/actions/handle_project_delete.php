<?php
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../developer_helper.php';

$company_id = $_SESSION['company_id'] ?? null;
$project_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$developer_id = isset($_GET['developer_id']) ? (int) $_GET['developer_id'] : 0;

if ($project_id > 0) {
    delete_project($conn, $project_id, $company_id);
}

header("Location: ../../public/developer_view.php?id=" . $developer_id);
exit();
?>
