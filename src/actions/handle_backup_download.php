<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../super_admin_helper.php';
require_once __DIR__ . '/../backup_helper.php';
require_super_admin();

$id = $_GET['id'] ?? null;
if (!$id) {
    http_response_code(404);
    exit('Not found');
}

// The filename is always resolved from the metadata table by id —
// never taken directly from the request — so this can't be used to
// traverse or download an arbitrary file.
$backup = get_backup_by_id($conn, $id);
if ($backup === null) {
    http_response_code(404);
    exit('Backup not found');
}

$path = backup_storage_dir() . '/' . basename($backup['filename']);
if (!is_file($path)) {
    http_response_code(404);
    exit('Backup file missing on disk');
}

header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . basename($backup['filename']) . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit();
