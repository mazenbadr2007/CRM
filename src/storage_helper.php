<?php
/**
 * Storage Limits helper.
 *
 * Each company has a storage quota, in gigabytes (companies.storage_limit_gb).
 * Usage is calculated live as the SUM of `file_size` in `client_documents`
 * for that company, so there's no separate running counter that could ever
 * drift out of sync with what's actually on disk.
 *
 * Follows the same self-healing pattern used across the codebase: the
 * `storage_limit_gb` column is added to `companies` automatically the
 * first time it's needed, no manual migration step required.
 */

// Default quota (GB) for companies that don't have an explicit limit set
// yet, so every company is protected even before a Super Admin visits
// the dashboard to configure it.
define('DEFAULT_COMPANY_STORAGE_LIMIT_GB', 5);

/**
 * Adds the storage_limit_gb column to companies the first time it's
 * needed. Safe to call repeatedly.
 */
function ensure_storage_limit_column($conn) {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $check = $conn->query("SHOW COLUMNS FROM `companies` LIKE 'storage_limit_gb'");
    if ($check && $check->num_rows === 0) {
        $conn->query("ALTER TABLE `companies` ADD COLUMN `storage_limit_gb` DECIMAL(10,2) DEFAULT NULL");
    }
}

/**
 * Returns a company's storage quota in GB. Falls back to the default
 * quota when no explicit limit has been set for that company.
 */
function get_company_storage_limit_gb($conn, $company_id) {
    ensure_storage_limit_column($conn);

    $stmt = $conn->prepare("SELECT storage_limit_gb FROM companies WHERE id = ?");
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->num_rows > 0 ? $result->fetch_assoc() : null;
    $stmt->close();

    if ($row === null || $row['storage_limit_gb'] === null) {
        return (float) DEFAULT_COMPANY_STORAGE_LIMIT_GB;
    }
    return (float) $row['storage_limit_gb'];
}

/**
 * Same as get_company_storage_limit_gb(), expressed in bytes.
 */
function get_company_storage_limit_bytes($conn, $company_id) {
    return (int) round(get_company_storage_limit_gb($conn, $company_id) * 1024 * 1024 * 1024);
}

/**
 * Total bytes currently used by a company's uploaded client documents.
 */
function get_company_storage_used_bytes($conn, $company_id) {
    ensure_company_id_columns($conn); // makes sure client_documents exists

    $stmt = $conn->prepare("SELECT COALESCE(SUM(file_size), 0) AS used FROM client_documents WHERE company_id = ?");
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $used = (int) $stmt->get_result()->fetch_assoc()['used'];
    $stmt->close();

    return $used;
}

/**
 * Bytes still available before the company hits its quota. Never
 * negative — if usage has somehow exceeded the limit (e.g. the limit
 * was lowered after files were already uploaded), this returns 0.
 */
function get_company_storage_available_bytes($conn, $company_id) {
    $available = get_company_storage_limit_bytes($conn, $company_id) - get_company_storage_used_bytes($conn, $company_id);
    return max(0, $available);
}

/**
 * Returns a full snapshot in one call (used / limit / available bytes
 * and the used percentage 0-100), so pages only need one query round
 * trip to render a storage widget.
 */
function get_company_storage_summary($conn, $company_id) {
    $limit = get_company_storage_limit_bytes($conn, $company_id);
    $used = get_company_storage_used_bytes($conn, $company_id);
    $available = max(0, $limit - $used);
    $percent_used = $limit > 0 ? min(100, round(($used / $limit) * 100, 1)) : 100;

    return [
        'limit_bytes'     => $limit,
        'used_bytes'      => $used,
        'available_bytes' => $available,
        'percent_used'    => $percent_used,
        'limit_reached'   => $used >= $limit,
    ];
}

/**
 * Whether a company has room for another file of $incoming_bytes
 * without going over its quota.
 */
function company_storage_has_room($conn, $company_id, $incoming_bytes = 0) {
    $limit = get_company_storage_limit_bytes($conn, $company_id);
    $used = get_company_storage_used_bytes($conn, $company_id);
    return ($used + max(0, (int) $incoming_bytes)) <= $limit;
}

/**
 * The professional, user-facing message shown when a company's storage
 * limit has been reached.
 */
function storage_limit_reached_message() {
    return "Your company's storage limit has been reached. Please delete some files or ask your administrator to increase the storage quota.";
}

/**
 * Human-readable size, auto-scaling between KB / MB / GB (e.g. "1.4 GB").
 */
function format_storage_bytes($bytes) {
    $bytes = (float) $bytes;
    if ($bytes >= 1024 * 1024 * 1024) {
        return round($bytes / (1024 * 1024 * 1024), 2) . ' GB';
    }
    if ($bytes >= 1024 * 1024) {
        return round($bytes / (1024 * 1024), 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }
    return $bytes . ' B';
}

/**
 * Sets (or clears, with null, which resets to the default quota) a
 * company's storage limit in GB. Called only from the Super Admin
 * dashboard.
 */
function admin_set_company_storage_limit($conn, $company_id, $storage_limit_gb) {
    ensure_storage_limit_column($conn);
    $company_id = (int) $company_id;
    $storage_limit_gb = ($storage_limit_gb === null || $storage_limit_gb === '') ? null : (float) $storage_limit_gb;

    if ($storage_limit_gb === null) {
        $stmt = $conn->prepare("UPDATE companies SET storage_limit_gb = NULL WHERE id = ?");
        $stmt->bind_param("i", $company_id);
    } else {
        $stmt = $conn->prepare("UPDATE companies SET storage_limit_gb = ? WHERE id = ?");
        $stmt->bind_param("di", $storage_limit_gb, $company_id);
    }
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}
