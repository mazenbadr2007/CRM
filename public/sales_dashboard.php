<?php
// Sales Dashboard — visible to every signed-in user, data scoped by role:
//   company admin -> company | sales manager -> own team | employee -> own data
require_once __DIR__ . '/../src/auth/auth_check.php';
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/sales_dashboard_helper.php';

$role = current_user_role();
$can_see_team = access_can_see_team();

$f = sales_dash_filters($_GET);
$data = get_sales_dashboard($conn, $f['from'], $f['to'], $f['employee_id'], $f['status']);
$rows = $data['rows'];
$tot  = $data['totals'];
$targets = get_sales_dashboard_targets($conn, $f['period'], $f['employee_id']);
$sd_employees = client_filter_employees($conn);
$sd_status_options = client_status_options();

$target_sum = 0;
$target_actual_sum = 0;
foreach ($targets as $t) {
    if ($t['target_leads'] > 0) {
        $target_sum += $t['target_leads'];
        $target_actual_sum += $t['actual_leads'];
    }
}
$target_pct = $target_sum > 0 ? (int) round($target_actual_sum / $target_sum * 100) : null;

if ($role === ROLE_COMPANY_ADMIN) {
    $scope_key = 'sd_scope_company'; $scope_txt = 'Company data';
} elseif ($role === ROLE_SALES_MANAGER) {
    $scope_key = 'sd_scope_team'; $scope_txt = 'My team';
} else {
    $scope_key = 'sd_scope_me'; $scope_txt = 'My data';
}

$funnel = [
    ['sd_leads', 'Leads', $tot['leads'], '#0d6efd'],
    ['sd_calls', 'Calls', $tot['calls'], '#6f42c1'],
    ['sd_visits', 'Visits', $tot['visits'], '#fd7e14'],
    ['sd_reservations', 'Reservations', $tot['reservations'], '#20c997'],
    ['sd_sales', 'Sales', $tot['sales'], '#198754'],
];
$fmt_pct = function ($v) { return rtrim(rtrim(number_format((float) $v, 1), '0'), '.') . '%'; };
$bar_class = function ($pct) { return $pct >= 100 ? 'bg-success' : ($pct >= 50 ? 'bg-primary' : 'bg-warning'); };

require_once __DIR__ . '/../src/templates/header.php';
?>
<link rel="stylesheet" href="css/manager_dashboard.css">
<link rel="stylesheet" href="css/sales_dashboard.css">

<div class="container-fluid">
    <div class="page-header">
        <h1 class="page-title"><span data-translate-key="sd_title">Sales Dashboard</span>
            <span class="badge bg-secondary fs-6 align-middle ms-2" data-translate-key="<?php echo $scope_key; ?>"><?php echo $scope_txt; ?></span>
        </h1>
        <p class="page-subtitle" data-translate-key="sd_subtitle">Leads, calls, visits, reservations, sales and target achievement.</p>
    </div>

    <!-- Filters -->
    <div class="card data-card mb-4">
        <div class="card-body">
            <form method="GET" class="d-flex flex-wrap align-items-center gap-2">
                <?php if ($can_see_team): ?>
                    <label class="small text-muted mb-0" for="sdEmployee" data-translate-key="cf_employee">Employee</label>
                    <select id="sdEmployee" name="employee_id" class="form-select form-select-sm w-auto">
                        <option value="" data-translate-key="cf_all_employees">All employees</option>
                        <?php foreach ($sd_employees as $emp): ?>
                            <option value="<?php echo (int) $emp['id']; ?>" <?php echo ($f['employee_id'] === (int) $emp['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($emp['username']); ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
                <label class="small text-muted mb-0" for="sdStatus" data-translate-key="cf_status">Lead Status</label>
                <select id="sdStatus" name="status" class="form-select form-select-sm w-auto">
                    <option value="" data-translate-key="cf_all_statuses">All statuses</option>
                    <?php foreach ($sd_status_options as $key => $opt): ?>
                        <option value="<?php echo htmlspecialchars($key); ?>" data-translate-key="<?php echo $opt[0]; ?>" <?php echo ($f['status'] === $key) ? 'selected' : ''; ?>><?php echo $opt[1]; ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="small text-muted mb-0" for="sdPeriod" data-translate-key="sd_month">Month</label>
                <input type="month" id="sdPeriod" name="period" class="form-control form-control-sm w-auto" value="<?php echo htmlspecialchars($f['period']); ?>">
                <label class="small text-muted mb-0" for="sdFrom" data-translate-key="tm_from">From</label>
                <input type="date" id="sdFrom" name="from" class="form-control form-control-sm w-auto" value="<?php echo $f['custom'] ? htmlspecialchars($f['from']) : ''; ?>">
                <label class="small text-muted mb-0" for="sdTo" data-translate-key="tm_to">To</label>
                <input type="date" id="sdTo" name="to" class="form-control form-control-sm w-auto" value="<?php echo $f['custom'] ? htmlspecialchars($f['to']) : ''; ?>">
                <button type="submit" class="btn btn-sm btn-primary" data-translate-key="tm_apply">Apply</button>
                <a href="sales_dashboard.php" class="btn btn-sm btn-outline-secondary" data-translate-key="sd_this_month">This month</a>
            </form>
            <div class="form-text mt-2">
                <span data-translate-key="sd_showing">Showing leads created</span>
                <?php echo htmlspecialchars($f['from'] ?: '…'); ?> → <?php echo htmlspecialchars($f['to'] ?: '…'); ?>.
                <span data-translate-key="sd_range_hint">A From/To range overrides the month for the figures; targets always use the month.</span>
            </div>
        </div>
    </div>

    <!-- KPI cards -->
    <div class="row g-4 mb-4 sd-kpis">
        <div class="col-6 col-lg-4">
            <div class="stat-card">
                <div class="stat-icon" style="background-color:#0d6efd"><i class="fa-solid fa-user-plus"></i></div>
                <div class="stat-info">
                    <h5 class="stat-title" data-translate-key="sd_leads">Leads</h5>
                    <p class="stat-number"><?php echo $tot['leads']; ?></p>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-4">
            <div class="stat-card">
                <div class="stat-icon" style="background-color:#6f42c1"><i class="fa-solid fa-phone"></i></div>
                <div class="stat-info">
                    <h5 class="stat-title" data-translate-key="sd_calls">Calls</h5>
                    <p class="stat-number"><?php echo $tot['calls']; ?></p>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-4">
            <div class="stat-card">
                <div class="stat-icon" style="background-color:#fd7e14"><i class="fa-solid fa-location-dot"></i></div>
                <div class="stat-info">
                    <h5 class="stat-title" data-translate-key="sd_visits">Visits</h5>
                    <p class="stat-number"><?php echo $tot['visits']; ?></p>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-4">
            <div class="stat-card">
                <div class="stat-icon" style="background-color:#20c997"><i class="fa-solid fa-bookmark"></i></div>
                <div class="stat-info">
                    <h5 class="stat-title" data-translate-key="sd_reservations">Reservations</h5>
                    <p class="stat-number"><?php echo $tot['reservations']; ?></p>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-4">
            <div class="stat-card">
                <div class="stat-icon" style="background-color:#198754"><i class="fa-solid fa-handshake"></i></div>
                <div class="stat-info">
                    <h5 class="stat-title" data-translate-key="sd_sales">Sales</h5>
                    <p class="stat-number"><?php echo $tot['sales']; ?></p>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-4">
            <div class="stat-card">
                <div class="stat-icon" style="background-color:#dc3545"><i class="fa-solid fa-percent"></i></div>
                <div class="stat-info">
                    <h5 class="stat-title" data-translate-key="sd_conversion">Conversion Rate</h5>
                    <p class="stat-number"><?php echo $fmt_pct($tot['conversion']); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Funnel -->
    <div class="card data-card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0" data-translate-key="sd_funnel">Sales Funnel</h5>
        </div>
        <div class="card-body">
            <?php foreach ($funnel as $step):
                $w = $tot['leads'] > 0 ? min(100, round($step[2] / $tot['leads'] * 100)) : 0; ?>
                <div class="sd-funnel-row">
                    <div class="sd-funnel-label" data-translate-key="<?php echo $step[0]; ?>"><?php echo $step[1]; ?></div>
                    <div class="sd-funnel-track">
                        <div class="sd-funnel-bar" style="width:<?php echo max($w, $step[2] > 0 ? 2 : 0); ?>%;background-color:<?php echo $step[3]; ?>"></div>
                    </div>
                    <div class="sd-funnel-value"><?php echo (int) $step[2]; ?> <small class="text-muted">(<?php echo $w; ?>%)</small></div>
                </div>
            <?php endforeach; ?>
            <div class="form-text mt-2" data-translate-key="sd_reservation_note">Reservations = leads in the Negotiation stage; Sales = leads in the Won stage.</div>
        </div>
    </div>

    <!-- Sales employee performance -->
    <div class="card data-card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0" data-translate-key="sd_perf_title">Sales Employee Performance</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th data-translate-key="employee">Employee</th>
                            <th class="text-center" data-translate-key="sd_leads">Leads</th>
                            <th class="text-center" data-translate-key="sd_calls">Calls</th>
                            <th class="text-center" data-translate-key="sd_visits">Visits</th>
                            <th class="text-center" data-translate-key="sd_reservations">Reservations</th>
                            <th class="text-center" data-translate-key="sd_sales">Sales</th>
                            <th class="text-center" data-translate-key="sd_conversion">Conversion Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="8" class="text-center text-muted p-4" data-translate-key="no_employee_data">No employee data to show.</td></tr>
                        <?php else: ?>
                            <?php foreach ($rows as $i => $r): ?>
                                <tr>
                                    <td class="text-muted"><?php echo $i + 1; ?></td>
                                    <td class="fw-semibold"><?php echo htmlspecialchars($r['username']); ?></td>
                                    <td class="text-center"><?php echo $r['leads']; ?></td>
                                    <td class="text-center"><?php echo $r['calls']; ?></td>
                                    <td class="text-center"><?php echo $r['visits']; ?></td>
                                    <td class="text-center"><?php echo $r['reservations']; ?></td>
                                    <td class="text-center"><?php echo $r['sales']; ?></td>
                                    <td class="text-center"><?php echo $fmt_pct($r['conversion']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (count($rows) > 1): ?>
                                <tr class="table-light fw-bold">
                                    <td></td>
                                    <td data-translate-key="sd_total">Total</td>
                                    <td class="text-center"><?php echo $tot['leads']; ?></td>
                                    <td class="text-center"><?php echo $tot['calls']; ?></td>
                                    <td class="text-center"><?php echo $tot['visits']; ?></td>
                                    <td class="text-center"><?php echo $tot['reservations']; ?></td>
                                    <td class="text-center"><?php echo $tot['sales']; ?></td>
                                    <td class="text-center"><?php echo $fmt_pct($tot['conversion']); ?></td>
                                </tr>
                            <?php endif; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Target vs Achievement -->
    <div class="card data-card mb-4">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h5 class="card-title mb-0">
                <span data-translate-key="sd_target_title">Target vs Achievement</span>
                <small class="text-muted">(<?php echo htmlspecialchars($f['period']); ?>)</small>
            </h5>
            <?php if ($can_see_team): ?>
                <a class="btn btn-sm btn-outline-primary"
                   href="<?php echo $role === ROLE_COMPANY_ADMIN ? 'manager_dashboard.php' : 'team_dashboard.php'; ?>"
                   data-translate-key="sd_set_targets">Set targets</a>
            <?php endif; ?>
        </div>
        <?php if ($target_pct !== null): ?>
            <div class="card-body border-bottom">
                <div class="d-flex justify-content-between small mb-1">
                    <span data-translate-key="sd_overall">Overall lead target</span>
                    <strong><?php echo $target_actual_sum . ' / ' . $target_sum . ' (' . $target_pct . '%)'; ?></strong>
                </div>
                <div class="progress" style="height:12px" role="progressbar" aria-valuenow="<?php echo min(100, $target_pct); ?>" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar <?php echo $bar_class($target_pct); ?>" style="width:<?php echo min(100, $target_pct); ?>%"></div>
                </div>
            </div>
        <?php endif; ?>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th data-translate-key="employee">Employee</th>
                            <th class="text-center" data-translate-key="sd_col_target">Target</th>
                            <th class="text-center" data-translate-key="sd_achieved">Achieved</th>
                            <th style="min-width:200px" data-translate-key="sd_col_progress">Progress</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($targets)): ?>
                            <tr><td colspan="4" class="text-center text-muted p-4" data-translate-key="no_employee_data">No employee data to show.</td></tr>
                        <?php else: ?>
                            <?php foreach ($targets as $t): ?>
                                <tr>
                                    <td class="fw-semibold"><?php echo htmlspecialchars($t['username']); ?></td>
                                    <td class="text-center"><?php echo $t['target_leads'] > 0 ? $t['target_leads'] : '—'; ?></td>
                                    <td class="text-center"><?php echo $t['actual_leads']; ?></td>
                                    <td>
                                        <?php if ($t['pct'] !== null): $w = min(100, $t['pct']); ?>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="progress flex-grow-1" style="height:8px" role="progressbar" aria-valuenow="<?php echo $w; ?>" aria-valuemin="0" aria-valuemax="100">
                                                    <div class="progress-bar <?php echo $bar_class($t['pct']); ?>" style="width:<?php echo $w; ?>%"></div>
                                                </div>
                                                <small class="text-muted"><?php echo $t['pct']; ?>%</small>
                                            </div>
                                        <?php else: ?>
                                            <small class="text-muted" data-translate-key="sd_no_target">No target set</small>
                                        <?php endif; ?>
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

<?php require_once __DIR__ . '/../src/templates/footer.php'; ?>
