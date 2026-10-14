<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../access_helper.php';
require_once __DIR__ . '/../notification_helper.php';
require_once __DIR__ . '/../reminder_helper.php';

header('Content-Type: application/json');

function notifications_json_response($ok, $data = []) {
    echo json_encode(array_merge(['success' => $ok], $data));
    exit();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    notifications_json_response(false, ['error' => 'Not authenticated']);
}

$user_id = $_SESSION['user_id'];
// Kept for helper signatures; actual visibility comes from the role system
// (admin = company, manager = own team, employee = own) inside the helpers.

// Live-generate any due/missed follow-up notifications for this user
// before returning results, so the list is always current.
generate_followup_notifications($conn, $user_id);

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'list';

    if ($action === 'unread_count') {
        $count = get_unread_notification_count($conn, $user_id);
        notifications_json_response(true, ['unread_count' => $count]);
    }

    if ($action === 'today_popup') {
        $due = get_todays_followup_alerts($conn, $user_id);
        notifications_json_response(true, ['due' => $due]);
    }

    // Default: full list + unread count together.
    $notifications = get_notifications($conn, $user_id);
    foreach ($notifications as &$n) {
        $meta = get_notification_type_meta($n['type']);
        $n['label'] = $meta['label'];
        $n['icon'] = $meta['icon'];
    }
    unset($n);
    $unread_count = get_unread_notification_count($conn, $user_id);
    notifications_json_response(true, ['notifications' => $notifications, 'unread_count' => $unread_count]);
}

if ($method === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'mark_read') {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($id <= 0) {
            http_response_code(400);
            notifications_json_response(false, ['error' => 'Invalid id']);
        }
        mark_notification_read($conn, $id, $user_id);
        $unread_count = get_unread_notification_count($conn, $user_id);
        notifications_json_response(true, ['unread_count' => $unread_count]);
    }

    if ($action === 'mark_all_read') {
        mark_all_notifications_read($conn, $user_id);
        notifications_json_response(true, ['unread_count' => 0]);
    }

    if ($action === 'alert_mark_done') {
        $client_id = isset($_POST['client_id']) ? (int) $_POST['client_id'] : 0;
        if ($client_id <= 0) {
            http_response_code(400);
            notifications_json_response(false, ['error' => 'Invalid client_id']);
        }
        if (!user_can_access_client($conn, $client_id)) {
            http_response_code(403);
            notifications_json_response(false, ['error' => 'Forbidden']);
        }
        mark_followup_alert_done($conn, $client_id, $user_id);
        log_reminder_event($conn, $client_id, $user_id, date('Y-m-d'), null, 'completed', 'Follow-up marked done from Smart Alert popup.');
        notifications_json_response(true, ['unread_count' => get_unread_notification_count($conn, $user_id)]);
    }

    if ($action === 'alert_snooze') {
        $client_id = isset($_POST['client_id']) ? (int) $_POST['client_id'] : 0;
        $minutes = isset($_POST['minutes']) ? (int) $_POST['minutes'] : 10;
        if ($client_id <= 0) {
            http_response_code(400);
            notifications_json_response(false, ['error' => 'Invalid client_id']);
        }
        if (!user_can_access_client($conn, $client_id)) {
            http_response_code(403);
            notifications_json_response(false, ['error' => 'Forbidden']);
        }
        $snoozed_until = snooze_followup_alert($conn, $client_id, $user_id, $minutes > 0 ? $minutes : 10);
        notifications_json_response(true, ['snoozed_until' => $snoozed_until]);
    }

    if ($action === 'alert_dismiss') {
        // Dismiss != Mark as Done: it only silences today's popup for this
        // client (until end of day) without marking the follow-up itself
        // as completed, so it can still surface again on its actual date.
        $client_id = isset($_POST['client_id']) ? (int) $_POST['client_id'] : 0;
        if ($client_id <= 0) {
            http_response_code(400);
            notifications_json_response(false, ['error' => 'Invalid client_id']);
        }
        if (!user_can_access_client($conn, $client_id)) {
            http_response_code(403);
            notifications_json_response(false, ['error' => 'Forbidden']);
        }
        $minutes_left_today = max(1, (int) ((strtotime('tomorrow') - time()) / 60));
        $snoozed_until = snooze_followup_alert($conn, $client_id, $user_id, $minutes_left_today);
        log_reminder_event($conn, $client_id, $user_id, date('Y-m-d'), null, 'dismissed', 'Follow-up alert dismissed from Smart Alert popup.');
        notifications_json_response(true, ['snoozed_until' => $snoozed_until]);
    }

    http_response_code(400);
    notifications_json_response(false, ['error' => 'Unknown action']);
}

http_response_code(405);
notifications_json_response(false, ['error' => 'Method not allowed']);
