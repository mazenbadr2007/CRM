<?php
// Set a monthly lead target for an employee in the caller's own team.
// POST: employee_id, target_leads, period (YYYY-MM, defaults to current month)
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

$employee_id = isset($_POST['employee_id']) ? (int) $_POST['employee_id'] : 0;
$period = isset($_POST['period']) && $_POST['period'] !== '' ? trim($_POST['period']) : date('Y-m');
$target = isset($_POST['target_leads']) && is_numeric($_POST['target_leads']) ? (int) $_POST['target_leads'] : -1;

$res = set_employee_lead_target($conn, $employee_id, $period, $target);
if (!$res['ok']) {
    http_response_code($res['error'] === 'employee_not_in_team' ? 403 : 400);
    echo json_encode(['success' => false, 'error' => $res['error']]);
    exit();
}
echo json_encode(['success' => true, 'employee_id' => $employee_id, 'period' => $period, 'target_leads' => $target]);
