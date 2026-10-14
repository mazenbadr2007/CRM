<?php
/**
 * Automatic backup cron entry point.
 *
 * This is the reliable way to get true "automatic" backups: point a
 * daily cron job (or Windows Task Scheduler) at this file via the PHP
 * CLI, e.g.:
 *
 *   0 3 * * *  php /path/to/CRM/src/cron_backup.php >> /var/log/crm_backup.log 2>&1
 *
 * It's intentionally CLI-only (it refuses to run over HTTP) so it
 * can't be triggered by an anonymous web request. It shares the exact
 * same backup routine, retention policy, and secure storage location
 * as the manual "Run Backup Now" button on the Super Admin dashboard —
 * the only difference is who/what triggered it.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/backup_helper.php';

$result = create_database_backup($conn, 'auto', 'system/cron');

if ($result['status'] === 'success') {
    echo "[" . date('Y-m-d H:i:s') . "] Backup succeeded: {$result['filename']} (" . format_backup_size($result['size_bytes']) . ")\n";
    exit(0);
}

echo "[" . date('Y-m-d H:i:s') . "] Backup FAILED: " . ($result['error_message'] ?? 'unknown error') . "\n";
exit(1);
