<?php
/**
 * GET /api/properties.php        -> list this company's properties
 * GET /api/properties.php?id=5   -> a single property (must belong to this company)
 *
 * Read-only by design (per the API scope: "Read properties").
 * Always scoped to the company_id resolved from the API key.
 */

require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_json_response(['error' => 'Method not allowed. This endpoint is read-only (GET).'], 405);
}

if (!empty($_GET['id'])) {
    $property = get_property($conn, (int) $_GET['id'], $company_id);
    if ($property === null) {
        api_json_response(['error' => 'Property not found.'], 404);
    }
    api_json_response(['data' => $property]);
}

$limit = isset($_GET['limit']) ? max(1, min(200, (int) $_GET['limit'])) : 50;
$offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;

ensure_properties_table($conn);
$stmt = $conn->prepare("SELECT * FROM properties WHERE company_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param("iii", $company_id, $limit, $offset);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$count_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM properties WHERE company_id = ?");
$count_stmt->bind_param("i", $company_id);
$count_stmt->execute();
$total = (int) $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

api_json_response([
    'data' => $rows,
    'meta' => ['total' => $total, 'limit' => $limit, 'offset' => $offset],
]);
