<?php
/**
 * Lead Source statistics endpoint (JSON).
 *
 * Polled by the Charts & Analytics dashboard to keep the Lead Source
 * chart/cards automatically up to date without a full page reload.
 */
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lead_source_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];
$company_id = $_SESSION['company_id'] ?? null;

$stats = get_lead_source_stats($conn, $company_id);

echo json_encode(array_merge(['success' => true], $stats));
exit();
