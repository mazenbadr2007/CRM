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

$document_id = isset($_POST['id']) ? (int) $_POST['id'] : (isset($_GET['id']) ? (int) $_GET['id'] : 0);

if ($document_id <= 0) {
    header('Location: clients_dashboard.php');
    exit();
}

$doc = get_document_by_id($conn, $document_id);
if (!$doc) {
    header('Location: clients_dashboard.php');
    exit();
}

$client_id = (int) $doc['client_id'];

// The document must belong to this company, and its client must be in the user's role scope.
if ((isset($doc['company_id']) && (int) $doc['company_id'] !== (int) $company_id)
    || !user_can_access_client($conn, $client_id)) {
    header("Location: client_timeline.php?id={$client_id}");
    exit();
}

$file_path = documents_storage_dir_for_client($client_id) . '/' . basename($doc['stored_name']);

// Delete scoped to the verified client + company; only touch the file if a row was really removed.
$del_stmt = $conn->prepare("DELETE FROM client_documents WHERE id = ? AND client_id = ? AND company_id = ?");
$del_stmt->bind_param("iii", $document_id, $client_id, $company_id);
$del_stmt->execute();
$deleted_rows = $del_stmt->affected_rows;
$del_stmt->close();

if ($deleted_rows < 1) {
    header("Location: client_timeline.php?id={$client_id}");
    exit();
}

if (is_file($file_path)) {
    @unlink($file_path);
}

// Log this deletion on the client's activity timeline, if available.
$logger_path = __DIR__ . '/../src/activity_logger.php';
if (file_exists($logger_path)) {
    require_once $logger_path;
    if (function_exists('log_activity')) {
        $employee_name = $_SESSION['username'] ?? 'Unknown';
        $category_label = DOCUMENT_CATEGORIES[$doc['category']] ?? $doc['category'];
        log_activity(
            $conn,
            $client_id,
            $user_id,
            $employee_name,
            'document_deleted',
            "Deleted a document (\"" . $doc['original_name'] . "\") from " . $category_label . "."
        );
    }
}

header("Location: client_timeline.php?id={$client_id}&doc_deleted=1");
exit();
