<?php
/**
 * Client Document Management helper.
 *
 * Lets each client have uploaded files organized into 4 categories:
 * Images, PDF files, Contracts, Voice Notes.
 *
 * Follows the same self-healing pattern used by activity_logger.php /
 * pipeline_helper.php: the table is created automatically at runtime if
 * it doesn't exist yet, so no manual migration step is required. This
 * file is self-contained and does not touch any existing table.
 */

// Canonical list of document categories.
define('DOCUMENT_CATEGORIES', [
    'image'    => 'Images',
    'pdf'      => 'PDF Files',
    'contract' => 'Contracts',
    'voice'    => 'Voice Notes',
]);

// Allowed file extensions per category (lower-case, without the dot).
define('DOCUMENT_ALLOWED_EXTENSIONS', [
    'image'    => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
    'pdf'      => ['pdf'],
    'contract' => ['pdf', 'doc', 'docx'],
    'voice'    => ['mp3', 'wav', 'm4a', 'ogg', 'aac'],
]);

// Max upload size, in bytes (20 MB).
define('DOCUMENT_MAX_SIZE', 20 * 1024 * 1024);

/**
 * Creates the client_documents table the first time it's needed.
 * Safe to call repeatedly (CREATE TABLE IF NOT EXISTS).
 */
function ensure_documents_table($conn) {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $sql = "CREATE TABLE IF NOT EXISTS `client_documents` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `client_id` int(11) NOT NULL,
        `user_id` int(11) DEFAULT NULL,
        `company_id` int(11) DEFAULT NULL,
        `category` varchar(20) NOT NULL,
        `original_name` varchar(255) NOT NULL,
        `stored_name` varchar(255) NOT NULL,
        `file_size` int(11) DEFAULT NULL,
        `mime_type` varchar(100) DEFAULT NULL,
        `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `client_id` (`client_id`),
        KEY `user_id` (`user_id`),
        KEY `company_id` (`company_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    $conn->query($sql);

    $check = $conn->query("SHOW COLUMNS FROM `client_documents` LIKE 'company_id'");
    if ($check && $check->num_rows === 0) {
        $conn->query("ALTER TABLE `client_documents` ADD COLUMN `company_id` int(11) DEFAULT NULL");
    }
}

/**
 * Absolute path to the base storage directory for client documents.
 * Kept outside /public's document root reach conceptually (it's still
 * inside the project folder, but all access to files goes exclusively
 * through document_download.php / document_delete.php, which enforce
 * ownership checks — files are never linked to directly).
 */
function documents_storage_base() {
    $base = dirname(__DIR__) . '/storage/client_documents';
    if (!is_dir($base)) {
        @mkdir($base, 0755, true);
    }
    return $base;
}

/**
 * Storage directory for one specific client (created on demand).
 */
function documents_storage_dir_for_client($client_id) {
    $dir = documents_storage_base() . '/' . (int) $client_id;
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

/**
 * Returns true if $category is one of the recognized document categories.
 */
function is_valid_document_category($category) {
    return array_key_exists($category, DOCUMENT_CATEGORIES);
}

/**
 * Validates an uploaded file's extension against the allowed list for its
 * category. Returns true/false.
 */
function is_allowed_document_extension($category, $filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $allowed = DOCUMENT_ALLOWED_EXTENSIONS[$category] ?? [];
    return in_array($ext, $allowed, true);
}

/**
 * Builds a random, collision-safe stored filename that preserves the
 * original extension. The original file name is kept separately in the
 * database for display purposes.
 */
function generate_stored_document_name($original_name) {
    $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    $random = bin2hex(random_bytes(16));
    return $ext !== '' ? "{$random}.{$ext}" : $random;
}

/**
 * Fetches all documents for a client, grouped by category
 * (image / pdf / contract / voice), newest first within each group.
 */
function get_client_documents($conn, $client_id) {
    ensure_documents_table($conn);
    $company_id = $_SESSION['company_id'] ?? null;

    $grouped = [];
    foreach (array_keys(DOCUMENT_CATEGORIES) as $cat) {
        $grouped[$cat] = [];
    }

    if ($company_id !== null) {
        $stmt = $conn->prepare("SELECT * FROM client_documents WHERE client_id = ? AND company_id = ? ORDER BY uploaded_at DESC, id DESC");
        $stmt->bind_param("ii", $client_id, $company_id);
    } else {
        $stmt = $conn->prepare("SELECT * FROM client_documents WHERE client_id = ? ORDER BY uploaded_at DESC, id DESC");
        $stmt->bind_param("i", $client_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if (isset($grouped[$row['category']])) {
            $grouped[$row['category']][] = $row;
        }
    }
    $stmt->close();

    return $grouped;
}

/**
 * Fetches a single document row by id, or null if not found.
 */
function get_document_by_id($conn, $document_id) {
    ensure_documents_table($conn);
    $company_id = $_SESSION['company_id'] ?? null;

    if ($company_id !== null) {
        $stmt = $conn->prepare("SELECT * FROM client_documents WHERE id = ? AND company_id = ?");
        $stmt->bind_param("ii", $document_id, $company_id);
    } else {
        $stmt = $conn->prepare("SELECT * FROM client_documents WHERE id = ?");
        $stmt->bind_param("i", $document_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $doc = $result->num_rows > 0 ? $result->fetch_assoc() : null;
    $stmt->close();

    return $doc;
}

/**
 * Human-readable file size (e.g. "1.4 MB").
 */
function format_document_size($bytes) {
    $bytes = (float) $bytes;
    if ($bytes >= 1024 * 1024) {
        return round($bytes / (1024 * 1024), 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }
    return $bytes . ' B';
}

/**
 * Returns a Font Awesome icon class for a document category.
 */
function document_category_icon($category) {
    switch ($category) {
        case 'image':
            return 'fa-solid fa-image';
        case 'pdf':
            return 'fa-solid fa-file-pdf';
        case 'contract':
            return 'fa-solid fa-file-contract';
        case 'voice':
            return 'fa-solid fa-microphone';
        default:
            return 'fa-solid fa-file';
    }
}
