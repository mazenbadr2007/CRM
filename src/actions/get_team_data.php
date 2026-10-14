<?php
// Read-only team data for managers: members, calls/follow-ups/visits/sales, targets.
// GET: period (YYYY-MM, optional), from / to (YYYY-MM-DD, optional)
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../manager_helper.php';

header('Content-Type: application/json');
require_team_lead_access(true);

$period = isset($_GET['period']) && is_valid_target_period($_GET['period']) ? $_GET['period'] : date('Y-m');
$from = $_GET['from'] ?? null;
$to = $_GET['to'] ?? null;

echo json_encode([
    'success' => true,
    'team' => get_team_members($conn),
    'report' => get_team_report($conn, $from, $to),
    'targets' => get_team_targets($conn, $period),
]);
