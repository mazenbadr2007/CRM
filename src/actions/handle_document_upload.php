<?php
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../access_helper.php';
require_once __DIR__ . '/../document_helper.php';
require_once __DIR__ . '/../storage_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../public/clients_dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'] ?? null;
$company_id = $_SESSION['company_id'] ?? null;

if (!$user_id) {
    header('Location: ../../public/login.php');
    exit();
}

$client_id = isset($_POST['client_id']) ? (int) $_POST['client_id'] : 0;
$category = $_POST['category'] ?? '';

if ($client_id <= 0 || !is_valid_document_category($category)) {
    header("Location: ../../public/client_timeline.php?id={$client_id}&doc_error=invalid_request");
    exit();
}

// Role-based visibility: admin = company, manager = own team, employee = own leads.
if (!user_can_access_client($conn, $client_id)) {
    header("Location: ../../public/clients_dashboard.php");
    exit();
}

if (!isset($_FILES['document']) || $_FILES['document']['error'] === UPLOAD_ERR_NO_FILE) {
    header("Location: ../../public/client_timeline.php?id={$client_id}&doc_error=no_file");
    exit();
}

$file = $_FILES['document'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    header("Location: ../../public/client_timeline.php?id={$client_id}&doc_error=upload_failed");
    exit();
}

if ($file['size'] <= 0 || $file['size'] > DOCUMENT_MAX_SIZE) {
    header("Location: ../../public/client_timeline.php?id={$client_id}&doc_error=too_large");
    exit();
}

$original_name = basename($file['name']);

if (!is_allowed_document_extension($category, $original_name)) {
    header("Location: ../../public/client_timeline.php?id={$client_id}&doc_error=invalid_type");
    exit();
}

if (!is_uploaded_file($file['tmp_name'])) {
    header("Location: ../../public/client_timeline.php?id={$client_id}&doc_error=upload_failed");
    exit();
}

// Enforce the company's storage quota — reject the upload before it ever
// touches disk if it would push the company over its limit.
if (!company_storage_has_room($conn, $company_id, $file['size'])) {
    header("Location: ../../public/client_timeline.php?id={$client_id}&doc_error=storage_limit");
    exit();
}

$stored_name = generate_stored_document_name($original_name);
$target_dir = documents_storage_dir_for_client($client_id);
$target_path = $target_dir . '/' . $stored_name;

if (!move_uploaded_file($file['tmp_name'], $target_path)) {
    header("Location: ../../public/client_timeline.php?id={$client_id}&doc_error=upload_failed");
    exit();
}

$mime_type = mime_content_type($target_path) ?: ($file['type'] ?? null);

ensure_documents_table($conn);
$company_id = $_SESSION['company_id'] ?? null;
$insert = $conn->prepare(
    "INSERT INTO client_documents (client_id, user_id, company_id, category, original_name, stored_name, file_size, mime_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
);
$insert->bind_param(
    "iiisssis",
    $client_id,
    $user_id,
    $company_id,
    $category,
    $original_name,
    $stored_name,
    $file['size'],
    $mime_type
);
$insert->execute();
$insert->close();

// Log this upload on the client's activity timeline, if the logger is
// available (additive only — does not affect any existing behavior).
$logger_path = __DIR__ . '/../activity_logger.php';
if (file_exists($logger_path)) {
    require_once $logger_path;
    if (function_exists('log_activity')) {
        $employee_name = $_SESSION['username'] ?? 'Unknown';
        $category_label = DOCUMENT_CATEGORIES[$category] ?? $category;
        log_activity(
            $conn,
            $client_id,
            $user_id,
            $employee_name,
            'document_uploaded',
            "Uploaded a document (\"" . $original_name . "\") to " . $category_label . "."
        );
    }
}

header("Location: ../../public/client_timeline.php?id={$client_id}&doc_success=1");
exit();
