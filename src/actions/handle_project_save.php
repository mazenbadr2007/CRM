<?php
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../developer_helper.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../../public/developers.php");
    exit();
}

$company_id = $_SESSION['company_id'] ?? null;
$developer_id = isset($_POST['developer_id']) ? (int) $_POST['developer_id'] : 0;

$data = [
    'name'        => trim($_POST['name'] ?? ''),
    'location'    => trim($_POST['location'] ?? ''),
    'description' => trim($_POST['description'] ?? ''),
];

if ($data['name'] === '' || $developer_id <= 0) {
    header("Location: ../../public/developer_view.php?id=" . $developer_id . "&error=missing_fields");
    exit();
}

// Confirm the developer belongs to this company before attaching a project.
$developer = get_developer($conn, $developer_id, $company_id);
if (!$developer) {
    header("Location: ../../public/developers.php");
    exit();
}

$project_id = isset($_POST['project_id']) ? (int) $_POST['project_id'] : 0;

if ($project_id > 0) {
    update_project($conn, $project_id, $company_id, $data);
} else {
    create_project($conn, $company_id, $developer_id, $data);
}

header("Location: ../../public/developer_view.php?id=" . $developer_id);
exit();
?>
