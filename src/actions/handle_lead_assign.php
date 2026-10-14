<?php
// Assign one or more leads to a user in the caller's own team.
// POST: client_id (or client_ids[]), assignee_id
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../manager_helper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

require_team_lead_access(true);

$assignee_id = isset($_POST['assignee_id']) ? (int) $_POST['assignee_id'] : 0;
$client_ids = [];
if (isset($_POST['client_ids']) && is_array($_POST['client_ids'])) {
    $client_ids = array_map('intval', $_POST['client_ids']);
} elseif (isset($_POST['client_id'])) {
    $client_ids = [(int) $_POST['client_id']];
}
$client_ids = array_values(array_unique(array_filter($client_ids, function ($id) { return $id > 0; })));

if ($assignee_id <= 0 || empty($client_ids) || count($client_ids) > 200) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit();
}

if (!can_assign_lead_to($conn, $assignee_id)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'You can only assign leads to your own team']);
    exit();
}

$assigned = [];
$failed = [];
foreach ($client_ids as $cid) {
    $res = assign_lead_to_user($conn, $cid, $assignee_id);
    if ($res['ok']) {
        $assigned[] = $cid;
    } else {
        $failed[] = ['client_id' => $cid, 'error' => $res['error']];
    }
}

if (empty($assigned)) {
    http_response_code(404);
}
echo json_encode(['success' => !empty($assigned), 'assigned' => $assigned, 'failed' => $failed]);
