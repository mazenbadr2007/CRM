<?php
require_once __DIR__ . '/../src/auth/auth_check.php';
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/company_settings_helper.php';

// A user may only ever view their own company's logo — company_id comes
// from the session, never from the request.
$company_id = $_SESSION['company_id'] ?? null;
$requested_file = $_GET['f'] ?? '';

if (!$company_id || $requested_file === '') {
    http_response_code(404);
    exit();
}

$settings = get_company_settings($conn, $company_id);

// The stored logo_path for this company must match the requested file
// exactly. This prevents a company from ever fetching another
// company's logo, even by guessing a filename.
if (empty($settings['logo_path']) || basename($settings['logo_path']) !== basename($requested_file)) {
    http_response_code(404);
    exit();
}

$path = company_logo_storage_dir($company_id) . '/' . basename($settings['logo_path']);

if (!is_file($path)) {
    http_response_code(404);
    exit();
}

$mime = mime_content_type($path) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Cache-Control: private, max-age=3600');
header('Content-Length: ' . filesize($path));
readfile($path);
exit();
