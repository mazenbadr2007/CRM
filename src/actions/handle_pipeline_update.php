<?php
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../activity_logger.php';
require_once __DIR__ . '/../pipeline_helper.php';
require_once __DIR__ . '/../notification_helper.php';

header('Content-Type: application/json');

function pipeline_json_response($ok, $data = []) {
    echo json_encode(array_merge(['success' => $ok], $data));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    pipeline_json_response(false, ['error' => 'Method not allowed']);
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    pipeline_json_response(false, ['error' => 'Not authenticated']);
}

$client_id = isset($_POST['client_id']) ? (int) $_POST['client_id'] : 0;
$new_stage = isset($_POST['new_stage']) ? trim((string) $_POST['new_stage']) : '';

if ($client_id <= 0 || !is_valid_pipeline_stage($new_stage)) {
    http_response_code(400);
    pipeline_json_response(false, ['error' => 'Invalid request']);
}

ensure_pipeline_stage_column($conn);

$user_id = $_SESSION['user_id'];
$company_id = $_SESSION['company_id'] ?? null;

// Role-based visibility: admin = company, manager = own team, employee = own leads.
$client = fetch_accessible_client($conn, $client_id, false);
if ($client === null) {
    http_response_code(404);
    pipeline_json_response(false, ['error' => 'Client not found']);
}

$old_stage = normalize_pipeline_stage($client['pipeline_stage'] ?? '');

if ($old_stage === $new_stage) {
    // Nothing actually changed (e.g. dropped back in the same column).
    pipeline_json_response(true, ['stage' => $new_stage, 'unchanged' => true]);
}

$update_stmt = $conn->prepare("UPDATE clients SET pipeline_stage = ? WHERE id = ? AND company_id = ?" . access_user_ids_sql($conn, 'user_id'));
$update_stmt->bind_param("sii", $new_stage, $client_id, $company_id);

if ($update_stmt->execute()) {
    $update_stmt->close();

    $employee_name = $_SESSION['username'] ?? 'Unknown';
    log_pipeline_stage_changed($conn, $client_id, $user_id, $employee_name, $old_stage, $new_stage);

    $client_name = $client['client_name'] ?? 'Client';
    $notify_owner_id = $client['user_id'] ?? $user_id;
    create_notification(
        $conn, $notify_owner_id, $client_id, 'status_changed',
        'Status Changed',
        "\"{$client_name}\" moved from \"{$old_stage}\" to \"{$new_stage}\" in the Sales Pipeline."
    );
    if ($new_stage === 'Won') {
        create_notification(
            $conn, $notify_owner_id, $client_id, 'sale_completed',
            'Sale Completed',
            "Sale completed for \"{$client_name}\" (Won)."
        );
    }

    pipeline_json_response(true, ['stage' => $new_stage]);
} else {
    $error = $conn->error;
    $update_stmt->close();
    http_response_code(500);
    pipeline_json_response(false, ['error' => 'Database error: ' . $error]);
}
