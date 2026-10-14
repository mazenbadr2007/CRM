<?php
require_once __DIR__ . '/../src/auth/auth_check.php';
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/document_helper.php';
require_once __DIR__ . '/../src/access_helper.php';

$user_id = $_SESSION['user_id'] ?? null;
// Role-based scope (access_helper.php): admin = company, manager = own team, employee = own assigned clients.
$company_id = $_SESSION['company_id'] ?? null;

if (!$user_id) {
    header('Location: login.php');
    exit();
}

$document_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$mode = ($_GET['mode'] ?? 'download') === 'preview' ? 'preview' : 'download';

if ($document_id <= 0) {
    http_response_code(404);
    exit('Document not found.');
}

$doc = get_document_by_id($conn, $document_id);
if (!$doc) {
    http_response_code(404);
    exit('Document not found.');
}

// The document must belong to this company, and its client must be in the user's role scope.
if ((isset($doc['company_id']) && (int) $doc['company_id'] !== (int) $company_id)
    || !user_can_access_client($conn, $doc['client_id'])) {
    http_response_code(403);
    exit('Access denied.');
}

$file_path = documents_storage_dir_for_client($doc['client_id']) . '/' . basename($doc['stored_name']);

if (!is_file($file_path)) {
    http_response_code(404);
    exit('File is missing from storage.');
}

$mime_type = $doc['mime_type'] ?: (mime_content_type($file_path) ?: 'application/octet-stream');

header('Content-Type: ' . $mime_type);
header('Content-Length: ' . filesize($file_path));
$disposition = $mode === 'preview' ? 'inline' : 'attachment';
$safe_name = str_replace(['"', "\r", "\n"], '', $doc['original_name']);
header('Content-Disposition: ' . $disposition . '; filename="' . $safe_name . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, must-revalidate');

readfile($file_path);
exit();
