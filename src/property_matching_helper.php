<?php
/**
 * Property Matching helper.
 *
 * Read-only: matches a set of buyer requirements against the existing
 * `properties` table (scoped by company_id like every other tenant-aware
 * table). No schema changes, no external services.
 *
 * Criteria (all optional, but at least one is needed to search):
 *   location  - text; matched against the project's location, or its name
 *   space     - m2; matched within +/- MATCH_SPACE_TOLERANCE
 *   price     - budget; matched within +/- MATCH_PRICE_TOLERANCE
 *   bedrooms  - exact number of bedrooms
 *   payment   - 'any' | 'cash' | 'installments'
 *               (cash never excludes a unit - any unit can be bought in
 *               cash; 'installments' keeps only units with an installment plan)
 */

require_once __DIR__ . '/property_helper.php';
require_once __DIR__ . '/developer_helper.php';

const MATCH_SPACE_TOLERANCE = 0.15;
const MATCH_PRICE_TOLERANCE = 0.15;
const MATCH_MAX_RESULTS = 50;

/**
 * Normalise raw request input into a clean criteria array.
 */
function property_matching_criteria(array $input) {
    $num = function ($key) use ($input) {
        $v = isset($input[$key]) ? trim((string) $input[$key]) : '';
        $v = str_replace(',', '', $v);
        return ($v !== '' && is_numeric($v) && (float) $v >= 0) ? (float) $v : null;
    };

    $payment = strtolower(trim((string) ($input['payment'] ?? 'any')));
    if (!in_array($payment, ['any', 'cash', 'installments'], true)) {
        $payment = 'any';
    }

    $bedrooms = $num('bedrooms');

    return [
        'location' => trim((string) ($input['location'] ?? '')),
        'space'    => $num('space'),
        'price'    => $num('price'),
        'bedrooms' => $bedrooms !== null ? (int) $bedrooms : null,
        'payment'  => $payment,
    ];
}

/**
 * True when there is at least one criterion to search by.
 */
function property_matching_has_criteria(array $c) {
    return $c['location'] !== '' || $c['space'] !== null || $c['price'] !== null
        || $c['bedrooms'] !== null || $c['payment'] !== 'any';
}

function _pm_like($value) {
    return '%' . addcslashes($value, '\\%_') . '%';
}

/**
 * Returns available properties that match the criteria, best match first.
 * Each row gets `project_location` and `match_score` (0-100).
 */
function match_properties($conn, $company_id, array $c) {
    ensure_properties_table($conn);
    ensure_developers_tables($conn);   // adds properties.project_id if missing

    if (!property_matching_has_criteria($c)) {
        return [];
    }

    // Location of the linked project; fall back to a project with the same
    // name for units saved before they were linked by project_id.
    $loc_sql = "COALESCE(
            (SELECT dp.location FROM developer_projects dp
              WHERE dp.id = p.project_id AND dp.company_id = p.company_id LIMIT 1),
            (SELECT dp2.location FROM developer_projects dp2
              WHERE dp2.name = p.project AND dp2.company_id = p.company_id
                AND dp2.location IS NOT NULL AND dp2.location <> '' LIMIT 1)
        )";

    $sql = "SELECT p.*, {$loc_sql} AS project_location
            FROM properties p
            WHERE p.company_id = ? AND p.status = 'available'";
    $types  = 'i';
    $params = [(int) $company_id];

    if ($c['location'] !== '') {
        $sql .= " AND ({$loc_sql} LIKE ? OR p.project LIKE ?)";
        $types .= 'ss';
        $like = _pm_like($c['location']);
        $params[] = $like;
        $params[] = $like;
    }
    if ($c['space'] !== null) {
        $sql .= " AND p.area BETWEEN ? AND ?";
        $types .= 'dd';
        $params[] = $c['space'] * (1 - MATCH_SPACE_TOLERANCE);
        $params[] = $c['space'] * (1 + MATCH_SPACE_TOLERANCE);
    }
    if ($c['price'] !== null) {
        $sql .= " AND p.price BETWEEN ? AND ?";
        $types .= 'dd';
        $params[] = $c['price'] * (1 - MATCH_PRICE_TOLERANCE);
        $params[] = $c['price'] * (1 + MATCH_PRICE_TOLERANCE);
    }
    if ($c['bedrooms'] !== null) {
        $sql .= " AND p.bedrooms = ?";
        $types .= 'i';
        $params[] = $c['bedrooms'];
    }
    if ($c['payment'] === 'installments') {
        $sql .= " AND p.installments IS NOT NULL AND TRIM(p.installments) <> ''";
    }

    $sql .= " ORDER BY p.price ASC LIMIT 500";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as &$row) {
        $row['match_score'] = _pm_score($row, $c);
    }
    unset($row);

    usort($rows, function ($a, $b) {
        if ($a['match_score'] === $b['match_score']) {
            return (float) $a['price'] <=> (float) $b['price'];
        }
        return $b['match_score'] <=> $a['match_score'];
    });

    return array_slice($rows, 0, MATCH_MAX_RESULTS);
}

/**
 * How close a unit is to the request, 0-100 (average over the criteria the
 * user actually entered). Used only for ordering and the badge.
 */
function _pm_score(array $row, array $c) {
    $parts = [];

    if ($c['location'] !== '') {
        $lower = function ($v) { return function_exists('mb_strtolower') ? mb_strtolower($v) : strtolower($v); };
        $loc = $lower(trim((string) ($row['project_location'] ?? '')));
        $parts[] = ($loc !== '' && $loc === $lower($c['location'])) ? 100 : 70;
    }
    if ($c['space'] !== null && $c['space'] > 0) {
        $diff = abs((float) $row['area'] - $c['space']) / ($c['space'] * MATCH_SPACE_TOLERANCE);
        $parts[] = max(0, 100 * (1 - min(1, $diff)));
    } elseif ($c['space'] !== null) {
        $parts[] = 100;
    }
    if ($c['price'] !== null && $c['price'] > 0) {
        $diff = abs((float) $row['price'] - $c['price']) / ($c['price'] * MATCH_PRICE_TOLERANCE);
        $parts[] = max(0, 100 * (1 - min(1, $diff)));
    } elseif ($c['price'] !== null) {
        $parts[] = 100;
    }
    if ($c['bedrooms'] !== null) {
        $parts[] = 100;
    }
    if ($c['payment'] !== 'any') {
        $parts[] = 100;
    }

    return $parts ? (int) round(array_sum($parts) / count($parts)) : 0;
}
