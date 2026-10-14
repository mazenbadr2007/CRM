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

$label = trim($_POST['label'] ?? '') ?: null;

// Always the session's own company — never accepts a company_id from
// the request, so a company can only ever create keys for itself.
$key = create_api_key($conn, $_SESSION['company_id'], $label);

// The plaintext key is shown exactly once, immediately after creation,
// via a one-time session flash value — never stored anywhere.
$_SESSION['_new_api_key_plaintext'] = $key['plaintext'];

header("Location: " . BASE_URL . "/settings.php");
exit();
