<?php
/**
 * Database Backup helper.
 *
 * Whole-system backups (every company's data in one dump) are a Super
 * Admin concern, not a per-company one — a company can already export
 * its own data through the normal CRM screens, but restoring/downloading
 * a full SQL dump of the entire `crm_system` database must never be
 * exposed to a regular company user. Every entry point in this file is
 * only ever called from super-admin-guarded pages/actions.
 *
 * Backups are stored OUTSIDE the public web root (in /storage/backups,
 * same private-storage pattern used for company logos and client
 * documents) and are only ever served through the authenticated
 * download endpoint below — the folder itself is never web-accessible
 * and every filename is validated against the metadata table before
 * anything is read from disk, so a filename can't be guessed/traversed
 * to leak another file.
 */

define('BACKUP_RETENTION_COUNT', 30); // keep the most recent N backups
define('BACKUP_MIN_INTERVAL_HOURS', 20); // auto-backup runs at most once/day

/**
 * Creates the backups metadata table the first time it's needed.
 * Self-healing, same pattern as the rest of the codebase.
 */
function ensure_backups_table($conn) {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $conn->query("CREATE TABLE IF NOT EXISTS `db_backups` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `filename` varchar(255) NOT NULL,
        `size_bytes` bigint(20) NOT NULL DEFAULT 0,
        `type` enum('manual','auto') NOT NULL DEFAULT 'manual',
        `status` enum('success','failed') NOT NULL DEFAULT 'success',
        `triggered_by` varchar(150) DEFAULT NULL,
        `error_message` varchar(500) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `filename` (`filename`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
}

/**
 * Absolute filesystem directory backups are written to. Created with
 * restrictive permissions on first use, and protected by its own
 * .htaccess (defense in depth — the app never serves this path
 * directly, but this blocks direct access if the vhost ever points
 * here by mistake).
 */
function backup_storage_dir() {
    $dir = __DIR__ . '/../storage/backups';
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }
    $htaccess = $dir . '/.htaccess';
    if (!is_file($htaccess)) {
        @file_put_contents($htaccess, "Require all denied\nDeny from all\n");
    }
    return $dir;
}

/**
 * Runs mysqldump for the whole application database and writes it to
 * a timestamped, randomized filename inside the private backup
 * directory. Returns the metadata row on success, or throws on
 * failure (caller decides how to surface that).
 *
 * $type is 'manual' or 'auto'; $triggered_by is a human label (super
 * admin username, or "system/cron") recorded for audit purposes.
 */
function create_database_backup($conn, $type = 'manual', $triggered_by = null) {
    ensure_backups_table($conn);

    global $db_host, $db_user, $db_pass, $db_name;

    $dir = backup_storage_dir();
    $timestamp = date('Ymd_His');
    $random = bin2hex(random_bytes(4));
    $filename = "backup_{$timestamp}_{$random}.sql";
    $target_path = $dir . '/' . $filename;

    // Build the mysqldump command. Credentials are passed via a
    // temporary defaults-extra-file rather than argv, so they never
    // show up in `ps` output or shell history.
    $defaults_file = tempnam(sys_get_temp_dir(), 'crmbk_');
    $defaults_contents = "[client]\n"
        . "host=" . $db_host . "\n"
        . "user=" . $db_user . "\n"
        . "password=" . $db_pass . "\n";
    file_put_contents($defaults_file, $defaults_contents);
    chmod($defaults_file, 0600);

    $cmd = sprintf(
        'mysqldump --defaults-extra-file=%s --single-transaction --quick --routines --triggers %s > %s 2> %s',
        escapeshellarg($defaults_file),
        escapeshellarg($db_name),
        escapeshellarg($target_path),
        escapeshellarg($target_path . '.err')
    );

    exec($cmd, $output, $exit_code);
    @unlink($defaults_file);

    $error_message = null;
    $success = ($exit_code === 0) && is_file($target_path) && filesize($target_path) > 0;

    if (!$success) {
        $error_message = is_file($target_path . '.err') ? trim(file_get_contents($target_path . '.err')) : 'mysqldump failed';
        $error_message = mb_substr($error_message, 0, 500);
        // mysqldump may not be available in every environment (e.g. a
        // restricted host). Fall back to a pure-PHP logical dump so a
        // backup can still be produced without a shell dependency.
        $fallback_ok = create_database_backup_fallback($conn, $target_path);
        $success = $fallback_ok;
        if (!$success) {
            if (is_file($target_path)) {
                @unlink($target_path);
            }
        } else {
            $error_message = null;
        }
    }
    @unlink($target_path . '.err');

    $size_bytes = $success ? filesize($target_path) : 0;
    $status = $success ? 'success' : 'failed';

    // Failed attempts leave no file, but are still logged so the
    // dashboard/audit trail shows the failure.
    $log_filename = $success ? $filename : ('FAILED_' . $filename);

    $stmt = $conn->prepare("INSERT INTO db_backups (filename, size_bytes, type, status, triggered_by, error_message) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sissss", $log_filename, $size_bytes, $type, $status, $triggered_by, $error_message);
    $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();

    if ($success) {
        enforce_backup_retention($conn);
    }

    return [
        'id' => $id,
        'filename' => $log_filename,
        'size_bytes' => $size_bytes,
        'type' => $type,
        'status' => $status,
        'error_message' => $error_message,
    ];
}

/**
 * Pure-PHP logical SQL dump (CREATE TABLE + INSERTs for every table),
 * used when the mysqldump binary isn't available on the host. Not as
 * feature-complete as mysqldump (no routines/triggers/views), but
 * enough to fully restore the application's data and schema.
 */
function create_database_backup_fallback($conn, $target_path) {
    $fp = @fopen($target_path, 'w');
    if (!$fp) {
        return false;
    }

    fwrite($fp, "-- CRM database backup (PHP fallback dump)\n");
    fwrite($fp, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
    fwrite($fp, "SET FOREIGN_KEY_CHECKS=0;\n\n");

    $tables_result = $conn->query("SHOW TABLES");
    if (!$tables_result) {
        fclose($fp);
        return false;
    }

    while ($row = $tables_result->fetch_row()) {
        $table = $row[0];

        $create = $conn->query("SHOW CREATE TABLE `{$table}`");
        if ($create) {
            $create_row = $create->fetch_row();
            fwrite($fp, "DROP TABLE IF EXISTS `{$table}`;\n");
            fwrite($fp, $create_row[1] . ";\n\n");
        }

        $data = $conn->query("SELECT * FROM `{$table}`");
        if ($data && $data->num_rows > 0) {
            $fields = $data->fetch_fields();
            $col_names = array_map(function ($f) { return "`{$f->name}`"; }, $fields);
            while ($data_row = $data->fetch_row()) {
                $values = array_map(function ($v) use ($conn) {
                    if ($v === null) {
                        return 'NULL';
                    }
                    return "'" . $conn->real_escape_string($v) . "'";
                }, $data_row);
                fwrite($fp, "INSERT INTO `{$table}` (" . implode(', ', $col_names) . ") VALUES (" . implode(', ', $values) . ");\n");
            }
            fwrite($fp, "\n");
        }
    }

    fwrite($fp, "SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($fp);

    return filesize($target_path) > 0;
}

/**
 * Deletes the oldest backups beyond BACKUP_RETENTION_COUNT, both from
 * disk and from the metadata table. Failed-attempt rows (no file on
 * disk) are excluded from the count and left for the audit trail.
 */
function enforce_backup_retention($conn) {
    ensure_backups_table($conn);

    $result = $conn->query("SELECT id, filename FROM db_backups WHERE status = 'success' ORDER BY created_at DESC");
    if (!$result) {
        return;
    }

    $rows = $result->fetch_all(MYSQLI_ASSOC);
    if (count($rows) <= BACKUP_RETENTION_COUNT) {
        return;
    }

    $to_delete = array_slice($rows, BACKUP_RETENTION_COUNT);
    $dir = backup_storage_dir();

    foreach ($to_delete as $row) {
        $path = $dir . '/' . basename($row['filename']);
        if (is_file($path)) {
            @unlink($path);
        }
        $stmt = $conn->prepare("DELETE FROM db_backups WHERE id = ?");
        $stmt->bind_param("i", $row['id']);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Runs an automatic backup only if enough time has passed since the
 * last one (auto OR manual — a recent manual backup still counts, so
 * we don't duplicate work). Safe to call on every page load from a
 * super-admin screen, or from a scheduled task. Returns the backup
 * row if one was created, or null if skipped.
 */
function run_auto_backup_if_due($conn) {
    ensure_backups_table($conn);

    $stmt = $conn->prepare("SELECT created_at FROM db_backups WHERE status = 'success' ORDER BY created_at DESC LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $last = $result->num_rows > 0 ? $result->fetch_assoc() : null;
    $stmt->close();

    if ($last !== null) {
        $last_time = strtotime($last['created_at']);
        $hours_since = (time() - $last_time) / 3600;
        if ($hours_since < BACKUP_MIN_INTERVAL_HOURS) {
            return null;
        }
    }

    return create_database_backup($conn, 'auto', 'system/auto-backup');
}

/**
 * Lists backup metadata, most recent first.
 */
function list_backups($conn, $limit = 100) {
    ensure_backups_table($conn);
    $limit = (int) $limit;
    $result = $conn->query("SELECT id, filename, size_bytes, type, status, triggered_by, error_message, created_at FROM db_backups ORDER BY created_at DESC LIMIT {$limit}");
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

/**
 * Looks up a single backup row by id — used by the download/delete
 * endpoints so a filename is always resolved from the trusted
 * metadata table, never taken directly from user input.
 */
function get_backup_by_id($conn, $id) {
    ensure_backups_table($conn);
    $id = (int) $id;
    $stmt = $conn->prepare("SELECT id, filename, size_bytes, type, status, created_at FROM db_backups WHERE id = ? AND status = 'success'");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->num_rows > 0 ? $result->fetch_assoc() : null;
    $stmt->close();
    return $row;
}

/**
 * Permanently deletes a backup file + its metadata row.
 */
function delete_backup($conn, $id) {
    ensure_backups_table($conn);
    $row = get_backup_by_id($conn, $id);
    if ($row === null) {
        return false;
    }

    $path = backup_storage_dir() . '/' . basename($row['filename']);
    if (is_file($path)) {
        @unlink($path);
    }

    $stmt = $conn->prepare("DELETE FROM db_backups WHERE id = ?");
    $stmt->bind_param("i", $row['id']);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/**
 * Tables that carry data scoped to a single company (via a direct
 * `company_id` column). This is the complete list of tenant-aware
 * tables created across the helper files (tenant_helper.php,
 * property_helper.php, developer_helper.php, installment_helper.php,
 * company_settings_helper.php) — every table a company's data lives
 * in. Used to build a per-company export that contains that company's
 * users, clients, properties, developers, projects, installments,
 * documents, reminders, notifications, activity log, and branding
 * settings, and nothing belonging to any other company.
 *
 * `users` and `clients` are included even though they started as
 * global tables in the base schema, because tenant_helper.php adds a
 * `company_id` column to both and every row is assigned one.
 */
function company_scoped_tables() {
    return [
        'users',
        'clients',
        'properties',
        'developers',
        'developer_projects',
        'property_installments',
        'client_documents',
        'client_reminders',
        'client_activity_log',
        'notifications',
        'company_settings',
    ];
}

/**
 * Builds a SQL export containing only one company's data: its own row
 * from `companies`, plus every row scoped to it (by company_id) across
 * every tenant-aware table. Used for the per-company "Backup Database"
 * button in Settings — never a super-admin-only whole-database dump,
 * so it's safe to expose to a regular company user for their own
 * company_id only. The caller must always pass the company_id taken
 * from the session, never from user input.
 *
 * Returns the SQL text as a string (small enough per company to hold
 * in memory and stream directly to the browser — no file is written
 * to disk, so there's nothing extra sitting in storage to secure or
 * clean up).
 */
function build_company_backup_sql($conn, $company_id) {
    ensure_company_id_columns($conn);
    ensure_company_settings_table($conn);

    $company_id = (int) $company_id;

    $sql = "-- Company data backup\n";
    $sql .= "-- Company ID: {$company_id}\n";
    $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    // The company's own row from `companies` (name, subscription, status).
    $sql .= dump_table_rows($conn, 'companies', 'id', $company_id);

    foreach (company_scoped_tables() as $table) {
        if (!table_exists($conn, $table)) {
            continue;
        }
        $sql .= dump_table_rows($conn, $table, 'company_id', $company_id);
    }

    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

    return $sql;
}

/**
 * Whether a table currently exists in the database. Guards against a
 * feature whose table hasn't been created yet (self-healing tables are
 * only created on first use of that feature).
 */
function table_exists($conn, $table) {
    $table_escaped = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '{$table_escaped}'");
    return $result && $result->num_rows > 0;
}

/**
 * Dumps every row of $table where $filter_column = $filter_value as
 * INSERT statements, prefixed with that table's CREATE TABLE
 * statement so the export is self-contained and restorable on its
 * own. Used for both the full company export above.
 */
function dump_table_rows($conn, $table, $filter_column, $filter_value) {
    $out = '';

    $create = $conn->query("SHOW CREATE TABLE `{$table}`");
    if (!$create) {
        return $out;
    }
    $create_row = $create->fetch_row();
    $out .= "-- --------------------------------------------------------\n\n";
    $out .= "DROP TABLE IF EXISTS `{$table}`;\n";
    $out .= $create_row[1] . ";\n\n";

    $filter_value = (int) $filter_value;
    $stmt = $conn->prepare("SELECT * FROM `{$table}` WHERE `{$filter_column}` = ?");
    if (!$stmt) {
        return $out;
    }
    $stmt->bind_param("i", $filter_value);
    $stmt->execute();
    $data = $stmt->get_result();

    if ($data && $data->num_rows > 0) {
        $fields = $data->fetch_fields();
        $col_names = array_map(function ($f) { return "`{$f->name}`"; }, $fields);
        while ($data_row = $data->fetch_row()) {
            $values = array_map(function ($v) use ($conn) {
                if ($v === null) {
                    return 'NULL';
                }
                return "'" . $conn->real_escape_string($v) . "'";
            }, $data_row);
            $out .= "INSERT INTO `{$table}` (" . implode(', ', $col_names) . ") VALUES (" . implode(', ', $values) . ");\n";
        }
        $out .= "\n";
    }
    $stmt->close();

    return $out;
}

/**
 * Human-readable file size, e.g. "4.2 MB".
 */
function format_backup_size($bytes) {
    $bytes = (int) $bytes;
    if ($bytes <= 0) {
        return '0 B';
    }
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = (int) floor(log($bytes, 1024));
    $i = max(0, min($i, count($units) - 1));
    return round($bytes / (1024 ** $i), 1) . ' ' . $units[$i];
}
