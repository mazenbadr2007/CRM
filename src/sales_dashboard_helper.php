<?php
/**
 * Sales Dashboard data (read-only, no schema changes).
 *
 * Scope comes from access_helper.php, exactly like the rest of the CRM:
 *   company_admin  -> whole company
 *   sales_manager  -> self + own team (manager_id = me)
 *   sales_employee -> own data only
 *
 * Metric definitions (same as the existing Team Report, so numbers agree):
 *   leads         clients created in the selected range
 *   calls         leads with a call outcome
 *   visits        leads with a visit outcome
 *   reservations  leads currently in a reservation stage (see below)
 *   sales         leads in the pipeline stage 'Won'
 *   conversion    sales / leads
 *
 * The pipeline has no dedicated "Reservation" stage; the stage right before
 * 'Won' (Negotiation) is used. Change this list to remap Reservations.
 */

require_once __DIR__ . '/manager_helper.php';   // access scope + lead targets
require_once __DIR__ . '/pipeline_helper.php';
require_once __DIR__ . '/client_filter_helper.php';   // Employee / Status filters

const SALES_DASH_RESERVATION_STAGES = ['Negotiation'];
const SALES_DASH_SALE_STAGE = 'Won';

function sales_dash_conversion($sales, $leads) {
    return $leads > 0 ? round(($sales / $leads) * 100, 1) : 0.0;
}

/**
 * Validated filters from a GET array.
 * period: YYYY-MM (drives targets, and the default date range)
 * from/to: optional YYYY-MM-DD override of the range for the KPIs/table.
 */
function sales_dash_filters(array $get) {
    $date_re = '/^\d{4}-\d{2}-\d{2}$/';
    $period = (isset($get['period']) && is_valid_target_period($get['period'])) ? $get['period'] : date('Y-m');

    $from = (isset($get['from']) && preg_match($date_re, $get['from'])) ? $get['from'] : '';
    $to   = (isset($get['to']) && preg_match($date_re, $get['to'])) ? $get['to'] : '';

    $custom = ($from !== '' || $to !== '');
    if (!$custom) {
        $from = $period . '-01';
        $to   = date('Y-m-t', strtotime($from));
    }
    $cf = client_filters_from_request($get);
    return ['period' => $period, 'from' => $from, 'to' => $to, 'custom' => $custom,
            'employee_id' => $cf['employee_id'], 'status' => $cf['status']];
}

/**
 * One row per person in the caller's scope + company/team totals.
 * @return array ['rows' => [...], 'totals' => [...]]
 */
function get_sales_dashboard($conn, $from, $to, $employee_id = 0, $status = '') {
    ensure_pipeline_stage_column($conn);

    $company_id = (int) ($_SESSION['company_id'] ?? 0);
    $join_extra = '';
    $types = 'i';
    $params = [$company_id];

    if ($from !== '') {
        $join_extra .= " AND c.created_at >= ?";
        $types .= 's';
        $params[] = $from . ' 00:00:00';
    }
    if ($to !== '') {
        $join_extra .= " AND c.created_at < DATE_ADD(?, INTERVAL 1 DAY)";
        $types .= 's';
        $params[] = $to . ' 00:00:00';
    }
    // Optional status filter (whitelisted SQL, no user input inside).
    $status_cond = $status !== '' ? client_status_sql($status, 'c') : null;
    if ($status_cond) {
        $join_extra .= " AND " . $status_cond;
    }

    $types .= 'i';
    $params[] = $company_id;

    $reserved = implode(',', array_map(function ($s) use ($conn) {
        return "'" . $conn->real_escape_string($s) . "'";
    }, SALES_DASH_RESERVATION_STAGES));
    $won = $conn->real_escape_string(SALES_DASH_SALE_STAGE);
    $admin_role = $conn->real_escape_string(ROLE_COMPANY_ADMIN);

    $scope = access_user_ids_sql($conn, 'u.id');
    // Employee filter narrows the role scope; an id outside it matches nothing.
    $emp_sql = '';
    if ((int) $employee_id > 0) {
        if (client_filter_employee_in_scope($conn, $employee_id)) {
            $emp_sql = " AND u.id = ?";
            $types .= 'i';
            $params[] = (int) $employee_id;
        } else {
            $emp_sql = " AND 1 = 0";
        }
    }
    $sql = "SELECT u.id, u.username, u.role,
                COUNT(c.id) AS leads,
                COALESCE(SUM(CASE WHEN c.call_outcome IS NOT NULL AND c.call_outcome <> '' THEN 1 ELSE 0 END), 0) AS calls,
                COALESCE(SUM(CASE WHEN c.visit_outcome IS NOT NULL AND c.visit_outcome <> '' THEN 1 ELSE 0 END), 0) AS visits,
                COALESCE(SUM(CASE WHEN c.pipeline_stage IN ({$reserved}) THEN 1 ELSE 0 END), 0) AS reservations,
                COALESCE(SUM(CASE WHEN c.pipeline_stage = '{$won}' THEN 1 ELSE 0 END), 0) AS sales
            FROM users u
            LEFT JOIN clients c ON c.user_id = u.id AND c.company_id = ?" . $join_extra . "
            WHERE u.company_id = ?" . $scope . $emp_sql . "
            GROUP BY u.id, u.username, u.role
            HAVING u.role <> '{$admin_role}' OR leads > 0
            ORDER BY sales DESC, leads DESC, u.username ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $totals = ['leads' => 0, 'calls' => 0, 'visits' => 0, 'reservations' => 0, 'sales' => 0];
    foreach ($rows as &$r) {
        foreach ($totals as $k => $_) {
            $r[$k] = (int) $r[$k];
            $totals[$k] += $r[$k];
        }
        $r['conversion'] = sales_dash_conversion($r['sales'], $r['leads']);
    }
    unset($r);
    $totals['conversion'] = sales_dash_conversion($totals['sales'], $totals['leads']);

    return ['rows' => $rows, 'totals' => $totals];
}

/**
 * Monthly lead target vs achievement (new leads created in that month) for
 * everyone in the caller's scope. Targets are the existing ones set from the
 * Manager Dashboard / My Team pages (employee_lead_targets).
 */
function get_sales_dashboard_targets($conn, $period, $employee_id = 0) {
    if (!is_valid_target_period($period)) {
        return [];
    }
    ensure_lead_targets_table($conn);

    $company_id = (int) ($_SESSION['company_id'] ?? 0);
    $from = $period . '-01 00:00:00';
    $to = date('Y-m-d 00:00:00', strtotime($period . '-01 +1 month'));
    $admin_role = $conn->real_escape_string(ROLE_COMPANY_ADMIN);
    $scope = access_user_ids_sql($conn, 'u.id');
    $emp_sql = '';
    $emp_id = 0;
    if ((int) $employee_id > 0) {
        if (client_filter_employee_in_scope($conn, $employee_id)) {
            $emp_id = (int) $employee_id;
            $emp_sql = " AND u.id = ?";
        } else {
            $emp_sql = " AND 1 = 0";
        }
    }

    $sql = "SELECT u.id, u.username, u.role,
                COALESCE(t.target_leads, 0) AS target_leads,
                (SELECT COUNT(*) FROM clients c
                  WHERE c.company_id = ? AND c.user_id = u.id
                    AND c.created_at >= ? AND c.created_at < ?) AS actual_leads
            FROM users u
            LEFT JOIN employee_lead_targets t
                   ON t.company_id = u.company_id AND t.user_id = u.id AND t.period_month = ?
            WHERE u.company_id = ? AND u.role <> '{$admin_role}'" . $scope . $emp_sql . "
            ORDER BY u.username ASC";

    $stmt = $conn->prepare($sql);
    if ($emp_id > 0) {
        $stmt->bind_param("isssii", $company_id, $from, $to, $period, $company_id, $emp_id);
    } else {
        $stmt->bind_param("isssi", $company_id, $from, $to, $period, $company_id);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as &$r) {
        $r['target_leads'] = (int) $r['target_leads'];
        $r['actual_leads'] = (int) $r['actual_leads'];
        $r['pct'] = $r['target_leads'] > 0 ? (int) round($r['actual_leads'] / $r['target_leads'] * 100) : null;
    }
    unset($r);
    return $rows;
}
