<?php
/**
 * REST API authentication helper.
 *
 * External systems authenticate with an API key (sent as a Bearer
 * token or X-API-Key header) instead of the session cookies used by
 * the rest of the CRM. Each key belongs to exactly one company, so
 * every API request is automatically scoped the same way the web UI
 * scopes a logged-in company user — a key can never see or create
 * data for any company but its own.
 *
 * Keys are stored as a salted hash (never in plaintext), same
 * principle as password storage elsewhere in this app. The plaintext
 * value is shown to the super admin exactly once, at creation time.
 */

define('API_KEY_PREFIX', 'crmapi_');

function ensure_api_keys_table($conn) {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $conn->query("CREATE TABLE IF NOT EXISTS `api_keys` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `company_id` int(11) NOT NULL,
        `label` varchar(150) DEFAULT NULL,
        `key_prefix` varchar(20) NOT NULL,
        `key_hash` varchar(255) NOT NULL,
        `status` enum('active','revoked') NOT NULL DEFAULT 'active',
        `last_used_at` timestamp NULL DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `company_id` (`company_id`),
        KEY `key_prefix` (`key_prefix`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
}

/**
 * Generates a new API key for a company. Returns the plaintext key
 * (shown once) plus the metadata row. Only the hash is persisted.
 */
function create_api_key($conn, $company_id, $label = null) {
    ensure_api_keys_table($conn);

    $company_id = (int) $company_id;
    $random = bin2hex(random_bytes(24));
    $plaintext = API_KEY_PREFIX . $random;
    $prefix_display = substr($plaintext, 0, 14); // shown in UI to identify the key without exposing it
    $hash = password_hash($plaintext, PASSWORD_BCRYPT);

    $stmt = $conn->prepare("INSERT INTO api_keys (company_id, label, key_prefix, key_hash) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $company_id, $label, $prefix_display, $hash);
    $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();

    return [
        'id' => $id,
        'plaintext' => $plaintext,
        'key_prefix' => $prefix_display,
        'company_id' => $company_id,
    ];
}

/**
 * Validates a presented API key. Returns the company_id it belongs to
 * on success, or null if the key is missing, malformed, revoked, or
 * doesn't match any stored hash.
 *
 * Keys are looked up by prefix first (indexed, cheap) then verified
 * with password_verify (constant-time, hash-based) — the same
 * defense-in-depth pattern used for user login passwords.
 */
function authenticate_api_key($conn, $presented_key) {
    ensure_api_keys_table($conn);

    if (!is_string($presented_key) || strpos($presented_key, API_KEY_PREFIX) !== 0) {
        return null;
    }

    $prefix_display = substr($presented_key, 0, 14);
    $stmt = $conn->prepare("SELECT id, company_id, key_hash FROM api_keys WHERE key_prefix = ? AND status = 'active'");
    $stmt->bind_param("s", $prefix_display);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as $row) {
        if (password_verify($presented_key, $row['key_hash'])) {
            $update = $conn->prepare("UPDATE api_keys SET last_used_at = NOW() WHERE id = ?");
            $update->bind_param("i", $row['id']);
            $update->execute();
            $update->close();
            return (int) $row['company_id'];
        }
    }

    return null;
}

function list_api_keys($conn, $company_id) {
    ensure_api_keys_table($conn);
    $company_id = (int) $company_id;
    $stmt = $conn->prepare("SELECT id, label, key_prefix, status, last_used_at, created_at FROM api_keys WHERE company_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function revoke_api_key($conn, $company_id, $key_id) {
    ensure_api_keys_table($conn);
    $company_id = (int) $company_id;
    $key_id = (int) $key_id;
    $stmt = $conn->prepare("UPDATE api_keys SET status = 'revoked' WHERE id = ? AND company_id = ?");
    $stmt->bind_param("ii", $key_id, $company_id);
    $stmt->execute();
    $ok = $stmt->affected_rows > 0;
    $stmt->close();
    return $ok;
}

/**
 * Extracts a presented API key from the request: Authorization:
 * Bearer <key>, or X-API-Key: <key>.
 */
function extract_presented_api_key() {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    // getallheaders() key casing varies by SAPI; normalize.
    $normalized = [];
    foreach ($headers as $k => $v) {
        $normalized[strtolower($k)] = $v;
    }

    if (!empty($normalized['x-api-key'])) {
        return trim($normalized['x-api-key']);
    }

    $auth = $normalized['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? null);
    if ($auth && stripos($auth, 'Bearer ') === 0) {
        return trim(substr($auth, 7));
    }

    return null;
}

/**
 * Call at the top of every API endpoint. Sends a 401 JSON error and
 * exits if authentication fails; otherwise returns the authenticated
 * company_id, which every endpoint must use to scope its queries —
 * never trust a company_id from the request body/query string.
 */
function require_api_auth($conn) {
    $presented = extract_presented_api_key();
    $company_id = $presented !== null ? authenticate_api_key($conn, $presented) : null;

    if ($company_id === null) {
        api_json_response(['error' => 'Unauthorized. Provide a valid API key via the Authorization: Bearer header or X-API-Key header.'], 401);
    }

    if (!is_subscription_active($conn, $company_id)) {
        api_json_response(['error' => 'This company\'s subscription is not active.'], 403);
    }

    return $company_id;
}

/**
 * Sends a JSON response with the given HTTP status and exits.
 */
function api_json_response($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}
