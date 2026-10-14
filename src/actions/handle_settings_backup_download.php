<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../access_helper.php';
require_company_admin();
require_once __DIR__ . '/../company_settings_helper.php';
require_once __DIR__ . '/../backup_helper.php';

// Always the session's own company — a company can only ever download
// its own data, never another company's. This never accepts a
// company_id from the request.
$company_id = (int) $_SESSION['company_id'];

$sql = build_company_backup_sql($conn, $company_id);

$timestamp = date('Ymd_His');
$filename = "company_{$company_id}_backup_{$timestamp}.sql";

header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($sql));
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
echo $sql;
exit();
