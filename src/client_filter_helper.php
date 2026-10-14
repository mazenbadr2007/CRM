<?php
/**
 * Advanced CRM filters (Employee + Status + Date), enforced in SQL.
 *
 * Scope is always applied by access_helper.php first:
 *   company_admin  -> whole company
 *   sales_manager  -> self + own team
 *   sales_employee -> own data only
 * and the filters below can only NARROW that scope: an employee_id outside
 * the caller's scope returns no rows (never "everything").
 *
 * Status -> data mapping (the CRM keeps two fields, so each status reads one):
 *   Interested / Not Interested / Follow-up  -> clients.final_status
 *   New / Contacted / Visit / Lost           -> clients.pipeline_stage
 *   Reservation                              -> pipeline stage 'Negotiation'
 *   Sale                                     -> pipeline stage 'Won'
 */

require_once __DIR__ . '/access_helper.php';
require_once __DIR__ . '/pipeline_helper.php';

/** key => [translation key, English label] (order = display order) */
function client_status_options() {
    return [
        'Interested'     => ['cf_st_interested', 'Interested'],
        'Not Interested' => ['cf_st_not_interested', 'Not Interested'],
        'Lost'           => ['cf_st_lost', 'Lost'],
        'Follow-up'      => ['cf_st_follow_up', 'Follow-up'],
        'New'            => ['cf_st_new', 'New'],
        'Contacted'      => ['cf_st_contacted', 'Contacted'],
        'Visit'          => ['cf_st_visit', 'Visit'],
        'Reservation'    => ['cf_st_reservation', 'Reservation'],
        'Sale'           => ['cf_st_sale', 'Sale'],
    ];
}

/**
 * SQL condition (no user input inside) for one status, for table alias $a.
 * Returns null for an unknown status.
 */
function client_status_sql($status, $a = 'c') {
    switch ($status) {
        case 'Interested':     return "{$a}.final_status = 'Interested'";
        case 'Not Interested': return "{$a}.final_status = 'Not Interested'";
        case 'Follow-up':      return "({$a}.final_status = 'Follow-up' OR {$a}.final_status IS NULL OR {$a}.final_status = '')";
        case 'Lost':           return "{$a}.pipeline_stage = 'Lost'";
        case 'New':            return "{$a}.pipeline_stage = 'New Lead'";
        case 'Contacted':      return "{$a}.pipeline_stage = 'Contacted'";
        case 'Visit':          return "{$a}.pipeline_stage = 'Visit Scheduled'";
        case 'Sale':           return "{$a}.pipeline_stage = 'Won'";
        case 'Reservation':
            $stages = defined('SALES_DASH_RESERVATION_STAGES') ? SALES_DASH_RESERVATION_STAGES : ['Negotiation'];
            $list = implode(',', array_map(function ($s) { return "'" . addslashes($s) . "'"; }, $stages));
            return "{$a}.pipeline_stage IN ({$list})";
    }
    return null;
}

function _cf_valid_date($d) {
    if (!is_string($d) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m)) {
        return '';
    }
    return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $d : '';
}

/** Validated filters from a GET array. */
function client_filters_from_request(array $get) {
    $status = isset($get['status']) ? trim((string) $get['status']) : '';
    if ($status !== '' && !array_key_exists($status, client_status_options())) {
        $status = '';
    }
    $from = _cf_valid_date($get['from'] ?? '');
    $to   = _cf_valid_date($get['to'] ?? '');
    if ($from !== '' && $to !== '' && $from > $to) {
        list($from, $to) = [$to, $from];
    }
    return [
        'employee_id' => isset($get['employee_id']) ? max(0, (int) $get['employee_id']) : 0,
        'status'      => $status,
        'from'        => $from,
        'to'          => $to,
    ];
}

function client_filters_active(array $f) {
    return $f['employee_id'] > 0 || $f['status'] !== '' || $f['from'] !== '' || $f['to'] !== '';
}

/** People the caller may filter by (own scope only), by name. */
function client_filter_employees($conn) {
    if (!access_can_see_team()) {
        return [];
    }
    ensure_user_role_columns($conn);
    $company_id = (int) ($_SESSION['company_id'] ?? 0);
    $admin_role = $conn->real_escape_string(ROLE_COMPANY_ADMIN);
    $scope = access_user_ids_sql($conn, 'u.id');

    $stmt = $conn->prepare(
        "SELECT u.id, u.username FROM users u
          WHERE u.company_id = ?" . $scope . "
            AND (u.role <> '{$admin_role}' OR EXISTS (SELECT 1 FROM clients c WHERE c.user_id = u.id AND c.company_id = u.company_id))
          ORDER BY u.username ASC"
    );
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/**
 * True when $employee_id is inside the caller's scope (or 0 = no filter).
 */
function client_filter_employee_in_scope($conn, $employee_id) {
    $employee_id = (int) $employee_id;
    if ($employee_id <= 0) {
        return true;
    }
    $ids = get_accessible_user_ids($conn);   // null = whole company (admin)
    return $ids === null || in_array($employee_id, $ids, true);
}

/**
 * Builds the WHERE fragment (alias c) + bind data shared by list/stats.
 * $with_status = false leaves the status out (used for the status facets).
 */
function _cf_where($conn, array $f, $with_status = true) {
    $company_id = (int) ($_SESSION['company_id'] ?? 0);
    $scope = access_user_filter($conn, 'c.user_id');

    $sql = " WHERE c.company_id = ?" . $scope['sql'];
    $types = 'i' . $scope['types'];
    $params = array_merge([$company_id], $scope['params']);

    if ($f['employee_id'] > 0) {
        if (!client_filter_employee_in_scope($conn, $f['employee_id'])) {
            $sql .= " AND 1 = 0";           // outside my scope -> nothing
        } else {
            $sql .= " AND c.user_id = ?";
            $types .= 'i';
            $params[] = $f['employee_id'];
        }
    }
    if ($with_status && $f['status'] !== '') {
        $cond = client_status_sql($f['status'], 'c');
        $sql .= $cond ? " AND " . $cond : " AND 1 = 0";
    }
    if ($f['from'] !== '') {
        $sql .= " AND c.created_at >= ?";
        $types .= 's';
        $params[] = $f['from'] . ' 00:00:00';
    }
    if ($f['to'] !== '') {
        $sql .= " AND c.created_at < DATE_ADD(?, INTERVAL 1 DAY)";
        $types .= 's';
        $params[] = $f['to'] . ' 00:00:00';
    }
    return [$sql, $types, $params];
}

/** Clients in the caller's scope that match the filters (newest first). */
function list_filtered_clients($conn, array $f, $with_employee_name = false) {
    ensure_pipeline_stage_column($conn);
    list($where, $types, $params) = _cf_where($conn, $f, true);

    if ($with_employee_name) {
        $sql = "SELECT c.*, u.username AS employee_name FROM clients c JOIN users u ON c.user_id = u.id"
             . $where . " ORDER BY c.created_at DESC";
    } else {
        $sql = "SELECT c.* FROM clients c" . $where . " ORDER BY c.created_at DESC";
    }
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/**
 * Statistics for the current Employee + Date filters, split by status.
 * 'total' ignores the status filter; 'by_status' counts each status inside
 * that same set, so the numbers move with the filters and stay clickable.
 */
function get_client_filter_stats($conn, array $f) {
    ensure_pipeline_stage_column($conn);
    list($where, $types, $params) = _cf_where($conn, $f, false);

    $cols = ['COUNT(*) AS total'];
    $i = 0;
    foreach (client_status_options() as $key => $_) {
        $cols[] = "COALESCE(SUM(CASE WHEN " . client_status_sql($key, 'c') . " THEN 1 ELSE 0 END), 0) AS s" . $i++;
    }
    $stmt = $conn->prepare("SELECT " . implode(', ', $cols) . " FROM clients c" . $where);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $by = [];
    $i = 0;
    foreach (client_status_options() as $key => $_) {
        $by[$key] = (int) $row['s' . $i++];
    }
    return ['total' => (int) $row['total'], 'by_status' => $by];
}
