<?php
// Company Admin only. Sales managers have their own page (team_dashboard.php).
require_once __DIR__ . '/../src/auth/manager_check.php';
require_once __DIR__ . '/../src/manager_helper.php';
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/helpers.php';

// Data scope: admin = whole company, manager = own team only (access_helper.php).
$company_id = $_SESSION['company_id'] ?? null;
$scope_users = access_user_ids_sql($conn, 'u.id');
$scope_clients_c = access_user_ids_sql($conn, 'c.user_id');
$scope_clients = access_user_ids_sql($conn, 'user_id');

$employee_performance_query = "
    SELECT u.username, COUNT(c.id) as client_count
    FROM users u
    LEFT JOIN clients c ON u.id = c.user_id AND c.company_id = ?
    WHERE u.role <> '" . ROLE_COMPANY_ADMIN . "' AND u.company_id = ?" . $scope_users . "
    GROUP BY u.id, u.username
    ORDER BY client_count DESC
";
$stmt = $conn->prepare($employee_performance_query);
$stmt->bind_param("ii", $company_id, $company_id);
$stmt->execute();
$employee_result = $stmt->get_result();
$employees = $employee_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$latest_clients_query = "
    SELECT c.client_name, c.final_status, u.username as employee_name
    FROM clients c
    JOIN users u ON c.user_id = u.id
    WHERE c.company_id = ?" . $scope_clients_c . "
    ORDER BY c.created_at DESC
    LIMIT 5
";
$stmt2 = $conn->prepare($latest_clients_query);
$stmt2->bind_param("i", $company_id);
$stmt2->execute();
$latest_clients_result = $stmt2->get_result();
$latest_clients = $latest_clients_result->fetch_all(MYSQLI_ASSOC);
$stmt2->close();

$viewer_id = (int) ($_SESSION['user_id'] ?? 0);
$total_stmt1 = $conn->prepare("SELECT COUNT(*) as count FROM users u WHERE u.role <> '" . ROLE_COMPANY_ADMIN . "' AND u.id <> ? AND u.company_id = ?" . $scope_users);
$total_stmt1->bind_param("ii", $viewer_id, $company_id);
$total_stmt1->execute();
$total_employees = $total_stmt1->get_result()->fetch_assoc()['count'];
$total_stmt1->close();

$total_stmt2 = $conn->prepare("SELECT COUNT(*) as count FROM clients WHERE company_id = ?" . $scope_clients);
$total_stmt2->bind_param("i", $company_id);
$total_stmt2->execute();
$total_clients = $total_stmt2->get_result()->fetch_assoc()['count'];
$total_stmt2->close();

// ---- Team tools: report / monthly lead targets / lead assignment ------------
// Everything below is scoped in manager_helper.php + access_helper.php:
//   sales_manager -> own team only, company_admin -> whole company.
$date_re = '/^\d{4}-\d{2}-\d{2}$/';
$report_from = (isset($_GET['from']) && preg_match($date_re, $_GET['from'])) ? $_GET['from'] : '';
$report_to   = (isset($_GET['to']) && preg_match($date_re, $_GET['to'])) ? $_GET['to'] : '';
$target_period = (isset($_GET['period']) && is_valid_target_period($_GET['period'])) ? $_GET['period'] : date('Y-m');

$team_report  = get_team_report($conn, $report_from ?: null, $report_to ?: null);
$team_targets = get_team_targets($conn, $target_period);
$assign_leads = list_accessible_clients($conn, true);

// Assignees: yourself + your team (admin: every non-admin user of the company).
$assignees = [['id' => (int) $_SESSION['user_id'], 'username' => (string) ($_SESSION['username'] ?? '')]];
foreach (get_team_members($conn) as $member) {
    $assignees[] = ['id' => (int) $member['id'], 'username' => $member['username']];
}

$report_totals = ['leads' => 0, 'calls' => 0, 'follow_ups' => 0, 'visits' => 0, 'sales' => 0];
foreach ($team_report as $r) {
    foreach ($report_totals as $k => $v) {
        $report_totals[$k] += (int) $r[$k];
    }
}

function md_role_key($role) {
    switch (normalize_user_role($role)) {
        case ROLE_COMPANY_ADMIN: return ['role_company_admin', 'Company Admin'];
        case ROLE_SALES_MANAGER: return ['role_sales_manager', 'Sales Manager'];
        default:                 return ['role_sales_employee', 'Sales Employee'];
    }
}
function md_conversion($sales, $leads) {
    return $leads > 0 ? round(($sales / $leads) * 100) . '%' : '0%';
}

require_once __DIR__ . '/../src/templates/header.php';
?>
<link rel="stylesheet" href="css/manager_dashboard.css">

<div class="container-fluid">
    <div class="page-header">
        <h1 class="page-title" data-translate-key="manager_dash_title">Manager Dashboard</h1>
        <p class="page-subtitle" data-translate-key="manager_dash_subtitle">An overview of your team's performance and system activity.</p>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="stat-card">
                <div class="stat-icon icon-employees">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div class="stat-info">
                    <h5 class="stat-title" data-translate-key="total_employees">Total Employees</h5>
                    <p class="stat-number"><?php echo $total_employees; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="stat-card">
                <div class="stat-icon icon-clients">
                    <i class="fa-solid fa-handshake"></i>
                </div>
                <div class="stat-info">
                    <h5 class="stat-title" data-translate-key="total_clients">Total Clients</h5>
                    <p class="stat-number"><?php echo $total_clients; ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card data-card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0" data-translate-key="employee_performance">Employee Performance</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th data-translate-key="employee">Employee</th>
                                    <th class="text-center" data-translate-key="clients_added">Clients Added</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($employees)): ?>
                                    <tr><td colspan="2" class="text-center text-muted p-4" data-translate-key="no_employee_data">No employee data to show.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($employees as $employee): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($employee['username']); ?></td>
                                            <td class="text-center fs-5 fw-bold"><?php echo $employee['client_count']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card data-card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0" data-translate-key="recent_activity">Recent Activity</h5>
                </div>
                <?php if (empty($latest_clients)): ?>
                    <div class="card-body text-center text-muted p-4" data-translate-key="no_recent_activity">No recent activity.</div>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($latest_clients as $client): ?>
                            <li class="list-group-item">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($client['client_name']); ?></h6>
                                    <small class="text-muted"><?php echo T($client['final_status']); ?></small>
                                </div>
                                <small class="text-muted"><span data-translate-key="added_by_label">Added by</span> <?php echo htmlspecialchars($client['employee_name']); ?></small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="teamToolMsg" class="alert d-none mt-4 mb-0" role="alert"></div>

    <!-- Team report: calls / follow-ups / visits / sales / conversion -->
    <div class="row g-4 mt-1">
        <div class="col-12">
            <div class="card data-card">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h5 class="card-title mb-0" data-translate-key="md_team_report_title">Team Report</h5>
                    <form method="GET" class="d-flex flex-wrap align-items-center gap-2">
                        <input type="hidden" name="period" value="<?php echo htmlspecialchars($target_period); ?>">
                        <label class="small text-muted mb-0" for="reportFrom" data-translate-key="md_filter_from">From</label>
                        <input type="date" id="reportFrom" name="from" class="form-control form-control-sm w-auto" value="<?php echo htmlspecialchars($report_from); ?>">
                        <label class="small text-muted mb-0" for="reportTo" data-translate-key="md_filter_to">To</label>
                        <input type="date" id="reportTo" name="to" class="form-control form-control-sm w-auto" value="<?php echo htmlspecialchars($report_to); ?>">
                        <button type="submit" class="btn btn-sm btn-primary" data-translate-key="md_filter_apply">Apply</button>
                    </form>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th data-translate-key="employee">Employee</th>
                                    <th data-translate-key="col_user_role">Role</th>
                                    <th class="text-center" data-translate-key="md_col_leads">Leads</th>
                                    <th class="text-center" data-translate-key="md_col_calls">Calls</th>
                                    <th class="text-center" data-translate-key="md_col_follow_ups">Follow-ups</th>
                                    <th class="text-center" data-translate-key="md_col_visits">Visits</th>
                                    <th class="text-center" data-translate-key="md_col_sales">Sales</th>
                                    <th class="text-center" data-translate-key="md_col_conversion">Conversion</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($team_report)): ?>
                                    <tr><td colspan="8" class="text-center text-muted p-4" data-translate-key="no_employee_data">No employee data to show.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($team_report as $r): $rk = md_role_key($r['role']); ?>
                                        <tr>
                                            <td class="fw-semibold"><?php echo htmlspecialchars($r['username']); ?></td>
                                            <td><span data-translate-key="<?php echo $rk[0]; ?>"><?php echo $rk[1]; ?></span></td>
                                            <td class="text-center"><?php echo (int) $r['leads']; ?></td>
                                            <td class="text-center"><?php echo (int) $r['calls']; ?></td>
                                            <td class="text-center"><?php echo (int) $r['follow_ups']; ?></td>
                                            <td class="text-center"><?php echo (int) $r['visits']; ?></td>
                                            <td class="text-center"><?php echo (int) $r['sales']; ?></td>
                                            <td class="text-center"><?php echo md_conversion((int) $r['sales'], (int) $r['leads']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="fw-bold">
                                        <td colspan="2" data-translate-key="md_total">Total</td>
                                        <td class="text-center"><?php echo $report_totals['leads']; ?></td>
                                        <td class="text-center"><?php echo $report_totals['calls']; ?></td>
                                        <td class="text-center"><?php echo $report_totals['follow_ups']; ?></td>
                                        <td class="text-center"><?php echo $report_totals['visits']; ?></td>
                                        <td class="text-center"><?php echo $report_totals['sales']; ?></td>
                                        <td class="text-center"><?php echo md_conversion($report_totals['sales'], $report_totals['leads']); ?></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly lead targets (own team only) -->
    <div class="row g-4 mt-1">
        <div class="col-lg-6">
            <div class="card data-card h-100">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h5 class="card-title mb-0" data-translate-key="md_targets_title">Monthly Lead Targets</h5>
                    <form method="GET" class="d-flex align-items-center gap-2">
                        <input type="hidden" name="from" value="<?php echo htmlspecialchars($report_from); ?>">
                        <input type="hidden" name="to" value="<?php echo htmlspecialchars($report_to); ?>">
                        <input type="month" name="period" class="form-control form-control-sm w-auto" value="<?php echo htmlspecialchars($target_period); ?>" onchange="this.form.submit()">
                    </form>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th data-translate-key="employee">Employee</th>
                                    <th data-translate-key="md_col_target">Target</th>
                                    <th class="text-center" data-translate-key="md_col_actual">Actual</th>
                                    <th data-translate-key="md_col_progress">Progress</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($team_targets)): ?>
                                    <tr><td colspan="5" class="text-center text-muted p-4" data-translate-key="md_no_team">No team members yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($team_targets as $t):
                                        $pct = $t['target_leads'] > 0 ? min(100, (int) round($t['actual_leads'] / $t['target_leads'] * 100)) : 0; ?>
                                        <tr>
                                            <td class="fw-semibold"><?php echo htmlspecialchars($t['username']); ?></td>
                                            <td style="width:110px">
                                                <input type="number" min="0" max="100000" class="form-control form-control-sm js-target-input"
                                                       data-employee="<?php echo (int) $t['user_id']; ?>" value="<?php echo (int) $t['target_leads']; ?>">
                                            </td>
                                            <td class="text-center"><?php echo (int) $t['actual_leads']; ?></td>
                                            <td style="min-width:110px">
                                                <?php if ($t['target_leads'] > 0): ?>
                                                    <div class="progress" style="height:8px" role="progressbar" aria-valuenow="<?php echo $pct; ?>" aria-valuemin="0" aria-valuemax="100">
                                                        <div class="progress-bar" style="width:<?php echo $pct; ?>%"></div>
                                                    </div>
                                                    <small class="text-muted"><?php echo $pct; ?>%</small>
                                                <?php else: ?>
                                                    <small class="text-muted">—</small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <button type="button" class="btn btn-sm btn-outline-primary js-save-target"
                                                        data-employee="<?php echo (int) $t['user_id']; ?>" data-translate-key="btn_save">Save</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assign leads (yourself or own team) -->
        <div class="col-lg-6">
            <div class="card data-card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0" data-translate-key="md_assign_title">Assign Leads</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($assign_leads)): ?>
                        <p class="text-center text-muted mb-0" data-translate-key="no_clients_found">No clients to display.</p>
                    <?php else: ?>
                        <input type="search" id="assignLeadFilter" class="form-control form-control-sm mb-2" placeholder="Search" data-translate-key="md_search_ph">
                        <div id="assignLeadList" class="border rounded p-2 mb-3" style="max-height:240px; overflow:auto;">
                            <?php foreach ($assign_leads as $lead): ?>
                                <label class="d-flex align-items-center gap-2 py-1 js-lead-row" data-search="<?php echo htmlspecialchars(mb_strtolower($lead['client_name'] . ' ' . ($lead['employee_name'] ?? ''))); ?>">
                                    <input type="checkbox" class="form-check-input mt-0 js-lead-check" value="<?php echo (int) $lead['id']; ?>">
                                    <span class="flex-grow-1"><?php echo htmlspecialchars($lead['client_name']); ?></span>
                                    <small class="text-muted"><?php echo htmlspecialchars($lead['employee_name'] ?? ''); ?></small>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <label class="mb-0 small text-muted" for="assignTo" data-translate-key="md_assign_to">Assign to</label>
                            <select id="assignTo" class="form-select form-select-sm w-auto">
                                <?php foreach ($assignees as $a): ?>
                                    <option value="<?php echo (int) $a['id']; ?>"><?php echo htmlspecialchars($a['username']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" id="assignBtn" class="btn btn-sm btn-primary" data-translate-key="md_assign_btn">Assign</button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Extra strings for this page (added before settings_manager.js applies the language).
document.addEventListener('DOMContentLoaded', function () {
    if (typeof translations === 'undefined') return;
    Object.assign(translations, {
        md_team_report_title: { en: 'Team Report', ar: 'تقرير الفريق' },
        md_filter_from: { en: 'From', ar: 'من' },
        md_filter_to: { en: 'To', ar: 'إلى' },
        md_filter_apply: { en: 'Apply', ar: 'تطبيق' },
        md_col_leads: { en: 'Leads', ar: 'العملاء' },
        md_col_calls: { en: 'Calls', ar: 'المكالمات' },
        md_col_follow_ups: { en: 'Follow-ups', ar: 'المتابعات' },
        md_col_visits: { en: 'Visits', ar: 'الزيارات' },
        md_col_sales: { en: 'Sales', ar: 'المبيعات' },
        md_col_conversion: { en: 'Conversion', ar: 'نسبة التحويل' },
        md_total: { en: 'Total', ar: 'الإجمالي' },
        md_targets_title: { en: 'Monthly Lead Targets', ar: 'أهداف العملاء الشهرية' },
        md_col_target: { en: 'Target', ar: 'الهدف' },
        md_col_actual: { en: 'Actual', ar: 'الفعلي' },
        md_col_progress: { en: 'Progress', ar: 'التقدم' },
        md_no_team: { en: 'No team members yet.', ar: 'لا يوجد أعضاء في الفريق بعد.' },
        md_assign_title: { en: 'Assign Leads', ar: 'توزيع العملاء' },
        md_assign_to: { en: 'Assign to', ar: 'تعيين إلى' },
        md_assign_btn: { en: 'Assign', ar: 'تعيين' },
        md_search_ph: { en: 'Search leads...', ar: 'ابحث عن عميل...' },
        md_msg_saved: { en: 'Target saved.', ar: 'تم حفظ الهدف.' },
        md_msg_assigned: { en: 'Leads assigned.', ar: 'تم تعيين العملاء.' },
        md_msg_partial: { en: 'Some leads could not be assigned.', ar: 'تعذر تعيين بعض العملاء.' },
        md_msg_select_lead: { en: 'Select at least one lead.', ar: 'اختر عميلًا واحدًا على الأقل.' },
        md_msg_denied: { en: 'Not allowed or failed.', ar: 'غير مسموح أو فشل الإجراء.' }
    });
});

(function () {
    const msg = document.getElementById('teamToolMsg');
    const t = (key, fb) => (window.tr ? window.tr(key, fb) : fb);
    function show(ok, key, fb) {
        msg.className = 'alert mt-4 mb-0 ' + (ok ? 'alert-success' : 'alert-danger');
        msg.textContent = t(key, fb);
        msg.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    function post(url, params) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params.toString()
        }).then(function (r) { return r.json().catch(function () { return { success: false }; }); })
          .catch(function () { return { success: false }; });
    }

    // Save one employee's monthly target (server re-checks team membership).
    document.querySelectorAll('.js-save-target').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id = btn.dataset.employee;
            const input = document.querySelector('.js-target-input[data-employee="' + id + '"]');
            const params = new URLSearchParams({
                employee_id: id,
                target_leads: input.value,
                period: <?php echo json_encode($target_period); ?>
            });
            post('../src/actions/handle_target_save.php', params).then(function (res) {
                if (res.success) { show(true, 'md_msg_saved', 'Target saved.'); setTimeout(function () { location.reload(); }, 700); }
                else { show(false, 'md_msg_denied', 'Not allowed or failed.'); }
            });
        });
    });

    // Lead list filter
    const filter = document.getElementById('assignLeadFilter');
    if (filter) {
        filter.addEventListener('input', function () {
            const q = filter.value.trim().toLowerCase();
            document.querySelectorAll('.js-lead-row').forEach(function (row) {
                row.style.display = row.dataset.search.indexOf(q) === -1 ? 'none' : '';
            });
        });
    }

    // Assign selected leads (server re-checks lead scope + assignee team).
    const assignBtn = document.getElementById('assignBtn');
    if (assignBtn) {
        assignBtn.addEventListener('click', function () {
            const ids = Array.from(document.querySelectorAll('.js-lead-check:checked')).map(function (c) { return c.value; });
            if (!ids.length) { show(false, 'md_msg_select_lead', 'Select at least one lead.'); return; }
            const params = new URLSearchParams();
            ids.forEach(function (id) { params.append('client_ids[]', id); });
            params.append('assignee_id', document.getElementById('assignTo').value);
            post('../src/actions/handle_lead_assign.php', params).then(function (res) {
                if (res.success && (!res.failed || !res.failed.length)) {
                    show(true, 'md_msg_assigned', 'Leads assigned.');
                    setTimeout(function () { location.reload(); }, 700);
                } else if (res.success) {
                    show(false, 'md_msg_partial', 'Some leads could not be assigned.');
                } else {
                    show(false, 'md_msg_denied', 'Not allowed or failed.');
                }
            });
        });
    }
})();
</script>

<?php
require_once __DIR__ . '/../src/templates/footer.php';
?>