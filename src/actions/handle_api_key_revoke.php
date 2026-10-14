<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../access_helper.php';
require_company_admin();
require_once __DIR__ . '/../api/api_auth_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "/settings.php");
    exit();
}

$id = $_POST['id'] ?? null;

if ($id) {
    // Scoped to the session's own company — a company can only ever
    // revoke its own keys, never another company's.
    revoke_api_key($conn, $_SESSION['company_id'], (int) $id);
}

header("Location: " . BASE_URL . "/settings.php");
exit();
