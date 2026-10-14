<?php
/**
 * GET  /api/clients.php        -> list this company's clients
 * GET  /api/clients.php?id=5   -> a single client (must belong to this company)
 * POST /api/clients.php        -> create a new client for this company
 *
 * Always scoped to the company_id resolved from the API key
 * (bootstrap.php) — never from request input, so one company's key
 * can never read or write another company's clients.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../lead_distribution_helper.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    handle_get_clients($conn, $company_id);
} elseif ($method === 'POST') {
    handle_create_client($conn, $company_id);
} else {
    api_json_response(['error' => 'Method not allowed. Use GET or POST.'], 405);
}

function handle_get_clients($conn, $company_id) {
    if (!empty($_GET['id'])) {
        $id = (int) $_GET['id'];
        $stmt = $conn->prepare("SELECT * FROM clients WHERE id = ? AND company_id = ?");
        $stmt->bind_param("ii", $id, $company_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->num_rows > 0 ? $result->fetch_assoc() : null;
        $stmt->close();

        if ($row === null) {
            api_json_response(['error' => 'Client not found.'], 404);
        }
        api_json_response(['data' => $row]);
    }

    // Basic pagination so a large client list can't be pulled in one
    // uncontrolled request.
    $limit = isset($_GET['limit']) ? max(1, min(200, (int) $_GET['limit'])) : 50;
    $offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;

    $stmt = $conn->prepare("SELECT * FROM clients WHERE company_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("iii", $company_id, $limit, $offset);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $count_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM clients WHERE company_id = ?");
    $count_stmt->bind_param("i", $company_id);
    $count_stmt->execute();
    $total = (int) $count_stmt->get_result()->fetch_assoc()['total'];
    $count_stmt->close();

    api_json_response([
        'data' => $rows,
        'meta' => ['total' => $total, 'limit' => $limit, 'offset' => $offset],
    ]);
}

function handle_create_client($conn, $company_id) {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!is_array($input)) {
        // Fall back to standard form encoding for convenience.
        $input = $_POST;
    }

    $client_name = trim($input['client_name'] ?? '');
    $phone_number = trim($input['phone_number'] ?? '');

    if ($client_name === '' || $phone_number === '') {
        api_json_response(['error' => 'client_name and phone_number are required.'], 422);
    }

    $project_location = trim($input['project_location'] ?? '');
    $project_name = trim($input['project_name'] ?? '');
    $unit_area = isset($input['unit_area']) && $input['unit_area'] !== '' ? (int) $input['unit_area'] : null;
    $the_role = trim($input['the_role'] ?? '');
    $receiving = trim($input['receiving'] ?? '');
    $unit_price = isset($input['unit_price']) && $input['unit_price'] !== '' ? (float) $input['unit_price'] : null;
    $down_payment = isset($input['down_payment']) && $input['down_payment'] !== '' ? (float) $input['down_payment'] : null;
    $installment = trim($input['installment'] ?? '');
    $installment_amount = isset($input['installment_amount']) && $input['installment_amount'] !== '' ? (float) $input['installment_amount'] : null;
    $visit = trim($input['visit'] ?? '');
    $call_outcome = trim($input['call_outcome'] ?? '');
    $visit_outcome = trim($input['visit_outcome'] ?? '');
    $final_status = trim($input['final_status'] ?? '');
    $event_title = trim($input['event_title'] ?? '');
    $event_date = !empty($input['event_date']) ? $input['event_date'] : null;
    $event_time = !empty($input['event_time']) ? $input['event_time'] : null;
    $note = trim($input['note'] ?? '');
    $lead_source = normalize_lead_source($input['lead_source'] ?? '');

    // Smart Lead Distribution: API leads are no longer left without an owner
    // (user_id 0). They go to the Sales Employee with the fewest open leads
    // (round robin on ties); with no employees yet they go to the company admin.
    // Decided in the same transaction as the INSERT
    // (assign_new_lead_owner opens it; commit/rollback below).
    $owner = assign_new_lead_owner($conn, $company_id, null, null);
    $api_user_id = $owner['user_id'];

    $stmt = $conn->prepare(
        "INSERT INTO clients (company_id, user_id, client_name, phone_number, project_location, project_name, unit_area, the_role, receiving, unit_price, down_payment, installment, installment_amount, visit, call_outcome, visit_outcome, final_status, event_title, event_date, event_time, note, lead_source)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        "iisssisssddsdsssssssss",
        $company_id, $api_user_id, $client_name, $phone_number, $project_location, $project_name,
        $unit_area, $the_role, $receiving, $unit_price, $down_payment,
        $installment, $installment_amount, $visit, $call_outcome, $visit_outcome,
        $final_status, $event_title, $event_date, $event_time, $note, $lead_source
    );

    try {
        $ok = $stmt->execute();
    } catch (Throwable $e) {
        $ok = false;
    }
    if (!$ok) {
        $stmt->close();
        $conn->rollback();
        api_json_response(['error' => 'Could not create client.'], 500);
    }

    $new_id = $stmt->insert_id;
    $stmt->close();
    $conn->commit();

    if ($api_user_id > 0) {
        announce_lead_assignment($conn, $company_id, $new_id, $client_name, $api_user_id, 0, 'API', $owner['mode']);
    }

    $select = $conn->prepare("SELECT * FROM clients WHERE id = ?");
    $select->bind_param("i", $new_id);
    $select->execute();
    $row = $select->get_result()->fetch_assoc();
    $select->close();

    api_json_response(['data' => $row], 201);
}
