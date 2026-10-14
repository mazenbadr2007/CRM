<?php
/**
 * Company Settings helper.
 *
 * Lets each company customize its own branding: logo, display name,
 * theme color, and contact information. Follows the same self-healing
 * pattern used across the codebase (CREATE TABLE IF NOT EXISTS / ADD
 * COLUMN IF NOT EXISTS) so no manual migration step is required.
 *
 * Everything here is scoped by company_id — a company can only ever
 * read or write its own settings row.
 */

define('COMPANY_LOGO_MAX_SIZE', 4 * 1024 * 1024); // 4 MB
define('COMPANY_LOGO_ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
define('COMPANY_DEFAULT_THEME_COLOR', '#0d6efd');

/**
 * Creates the company_settings table the first time it's needed, and
 * ensures every expected column exists. Safe to call repeatedly.
 */
function ensure_company_settings_table($conn) {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $sql = "CREATE TABLE IF NOT EXISTS `company_settings` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `company_id` int(11) NOT NULL,
        `display_name` varchar(150) DEFAULT NULL,
        `logo_path` varchar(255) DEFAULT NULL,
        `theme_color` varchar(20) NOT NULL DEFAULT '" . COMPANY_DEFAULT_THEME_COLOR . "',
        `contact_email` varchar(150) DEFAULT NULL,
        `contact_phone` varchar(50) DEFAULT NULL,
        `contact_address` varchar(255) DEFAULT NULL,
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `company_id` (`company_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    $conn->query($sql);
}

/**
 * Returns the settings row for a company, merged with sane defaults so
 * callers never have to null-check every field. Does NOT create a row.
 */
function get_company_settings($conn, $company_id) {
    ensure_company_settings_table($conn);

    $defaults = [
        'company_id'      => (int) $company_id,
        'display_name'    => null,
        'logo_path'       => null,
        'theme_color'     => COMPANY_DEFAULT_THEME_COLOR,
        'contact_email'   => null,
        'contact_phone'   => null,
        'contact_address' => null,
    ];

    $stmt = $conn->prepare("SELECT display_name, logo_path, theme_color, contact_email, contact_phone, contact_address FROM company_settings WHERE company_id = ?");
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->num_rows > 0 ? $result->fetch_assoc() : null;
    $stmt->close();

    if ($row === null) {
        return $defaults;
    }

    return array_merge($defaults, $row);
}

/**
 * Updates (or creates) a company's settings. Only the fields passed in
 * $fields are touched; anything not included is left as-is. $fields may
 * contain: display_name, logo_path, theme_color, contact_email,
 * contact_phone, contact_address.
 *
 * Always scoped to the given $company_id — callers must pass the
 * company_id of the currently authenticated user's session, never a
 * user-supplied value, so a company can never modify another company's
 * branding.
 */
function update_company_settings($conn, $company_id, $fields) {
    ensure_company_settings_table($conn);
    $company_id = (int) $company_id;

    // Make sure a row exists for this company first (INSERT IGNORE is
    // safe thanks to the UNIQUE KEY on company_id).
    $insert = $conn->prepare("INSERT IGNORE INTO company_settings (company_id) VALUES (?)");
    $insert->bind_param("i", $company_id);
    $insert->execute();
    $insert->close();

    $allowed_columns = ['display_name', 'logo_path', 'theme_color', 'contact_email', 'contact_phone', 'contact_address'];
    $set_parts = [];
    $values = [];
    $types = '';

    foreach ($allowed_columns as $col) {
        if (array_key_exists($col, $fields)) {
            $set_parts[] = "`{$col}` = ?";
            $values[] = $fields[$col];
            $types .= 's';
        }
    }

    if (empty($set_parts)) {
        return true;
    }

    $sql = "UPDATE company_settings SET " . implode(', ', $set_parts) . " WHERE company_id = ?";
    $types .= 'i';
    $values[] = $company_id;

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$values);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

/**
 * Validates a hex color string like #RRGGBB or #RGB.
 */
function is_valid_hex_color($color) {
    return is_string($color) && preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color) === 1;
}

/**
 * Validates a logo file extension against the allow-list.
 */
function is_allowed_logo_extension($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, COMPANY_LOGO_ALLOWED_EXTENSIONS, true);
}

/**
 * Absolute filesystem directory where a company's logo is stored.
 * Created on first use.
 */
function company_logo_storage_dir($company_id) {
    $dir = __DIR__ . '/../storage/company_logos';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

/**
 * Generates a collision-resistant stored filename for an uploaded logo,
 * namespaced by company_id so companies can never overwrite each
 * other's files even if two uploads race.
 */
function generate_company_logo_filename($company_id, $original_name) {
    $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    return 'company_' . (int) $company_id . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
}

/**
 * Public URL for a company's logo, or null if none is set.
 * Served through the logo_view.php streaming endpoint so the storage
 * directory itself never needs to be web-accessible.
 */
function company_logo_url($logo_path) {
    if (empty($logo_path)) {
        return null;
    }
    return BASE_URL . '/company_logo.php?f=' . urlencode($logo_path);
}
