<?php
// "My Team" — the Sales Manager's own workspace.
// Sales managers only: company admins use manager_dashboard.php, employees are sent home.
require_once __DIR__ . '/../src/auth/team_check.php';
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/reminder_helper.php';

// Everything below is scoped by access_helper.php / manager_helper.php:
// company_id + manager_id (own team) + assigned user_id. Nothing company-wide is read here.
$viewer_id = (int) $_SESSION['user_id'];
$team      = get_team_members($conn);                       // users whose manager_id = me
$team_ids  = array_map('intval', array_column($team, 'id'));
$team_names = [];
foreach ($team as $m) {
    $team_names[(int) $m['id']] = $m['username'];
}

$date_re = '/^\d{4}-\d{2}-\d{2}$/';
$report_from = (isset($_GET['from']) && preg_match($date_re, $_GET['from'])) ? $_GET['from'] : '';
$report_to   = (isset($_GET['to']) && preg_match($date_re, $_GET['to'])) ? $_GET['to'] : '';
$period      = (isset($_GET['period']) && is_valid_target_period($_GET['period'])) ? $_GET['period'] : date('Y-m');

// Per-employee report (calls / follow-ups / visits / sales) — team members only.
$report_by_user = [];
foreach (get_team_report($conn, $report_from ?: null, $report_to ?: null) as $r) {
    if (in_array((int) $r['id'], $team_ids, true)) {
        $report_by_user[(int) $r['id']] = $r;
    }
}
$targets_by_user = [];
foreach (get_team_targets($conn, $period) as $t) {
    $targets_by_user[(int) $t['user_id']] = $t;
}

// Follow-ups of the team (overdue / due today).
$overdue_by_user = [];
$overdue_list = [];
$due_today = 0;
foreach (get_active_reminders($conn) as $rem) {
    $uid = (int) $rem['user_id'];
    if (!in_array($uid, $team_ids, true)) {
        continue;
    }
    if ($rem['reminder_status'] === 'overdue') {
        $overdue_by_user[$uid] = ($overdue_by_user[$uid] ?? 0) + 1;
        $overdue_list[] = $rem;
    } elseif ($rem['reminder_status'] === 'today') {
        $due_today++;
    }
}
$overdue_total = count($overdue_list);
$overdue_list = array_slice($overdue_list, 0, 8);

// Leads I can distribute / monitor: my own + my team's.
$all_leads = array_slice(list_accessible_clients($conn, true), 0, 500);
$team_lead_count = 0;
$waiting_count = 0;
foreach ($all_leads as $l) {
    if ((int) $l['user_id'] === $viewer_id) {
        $waiting_count++;
    } elseif (in_array((int) $l['user_id'], $team_ids, true)) {
        $team_lead_count++;
    }
}

$assignees = [];
foreach ($team as $m) {
    $assignees[] = ['id' => (int) $m['id'], 'username' => $m['username']];
}
$assignees[] = ['id' => $viewer_id, 'username' => (string) ($_SESSION['username'] ?? '')];

$stage_keys = [
    'New Lead' => 'New_Lead', 'Contacted' => 'Contacted', 'Interested' => 'Interested',
    'Visit Scheduled' => 'Pipeline_Visit_Scheduled', 'Negotiation' => 'Negotiation',
    'Won' => 'Won', 'Lost' => 'Lost',
];

function tm_conversion($sales, $leads) {
    return $leads > 0 ? round(($sales / $leads) * 100) . '%' : '0%';
}

require_once __DIR__ . '/../src/templates/header.php';
?>
<link rel="stylesheet" href="css/manager_dashboard.css">

<div class="container-fluid">
    <div class="page-header">
        <h1 class="page-title" data-translate-key="tm_title">My Team</h1>
        <p class="page-subtitle" data-translate-key="tm_subtitle">Distribute leads to your employees and monitor your team only.</p>
    </div>

    <div id="teamMsg" class="alert d-none mb-4" role="alert"></div>

    <!-- Snapshot -->
    <div class="row g-4 mb-4">
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon icon-employees"><i class="fa-solid fa-people-group"></i></div>
                <div class="stat-info">
                    <h5 class="stat-title" data-translate-key="tm_stat_members">Team Members</h5>
                    <p class="stat-number"><?php echo count($team); ?></p>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon icon-clients"><i class="fa-solid fa-handshake"></i></div>
                <div class="stat-info">
                    <h5 class="stat-title" data-translate-key="tm_stat_team_leads">Team Leads</h5>
                    <p class="stat-number"><?php echo $team_lead_count; ?></p>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon" style="background-color:#6f42c1"><i class="fa-solid fa-inbox"></i></div>
                <div class="stat-info">
                    <h5 class="stat-title" data-translate-key="tm_stat_waiting">Waiting for Assignment</h5>
                    <p class="stat-number"><?php echo $waiting_count; ?></p>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon" style="background-color:#fd7e14"><i class="fa-solid fa-bell"></i></div>
                <div class="stat-info">
                    <h5 class="stat-title" data-translate-key="tm_stat_overdue">Overdue Follow-ups</h5>
                    <p class="stat-number"><?php echo $overdue_total; ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Team performance + monthly lead targets -->
    <div class="card data-card mb-4">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h5 class="card-title mb-0" data-translate-key="tm_team_title">Team Performance</h5>
            <form method="GET" class="d-flex flex-wrap align-items-center gap-2">
                <label class="small text-muted mb-0" for="tmFrom" data-translate-key="tm_from">From</label>
                <input type="date" id="tmFrom" name="from" class="form-control form-control-sm w-auto" value="<?php echo htmlspecialchars($report_from); ?>">
                <label class="small text-muted mb-0" for="tmTo" data-translate-key="tm_to">To</label>
                <input type="date" id="tmTo" name="to" class="form-control form-control-sm w-auto" value="<?php echo htmlspecialchars($report_to); ?>">
                <label class="small text-muted mb-0" for="tmPeriod" data-translate-key="tm_target_month">Target month</label>
                <input type="month" id="tmPeriod" name="period" class="form-control form-control-sm w-auto" value="<?php echo htmlspecialchars($period); ?>">
                <button type="submit" class="btn btn-sm btn-primary" data-translate-key="tm_apply">Apply</button>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th data-translate-key="employee">Employee</th>
                            <th class="text-center" data-translate-key="tm_col_leads">Leads</th>
                            <th class="text-center" data-translate-key="tm_col_calls">Calls</th>
                            <th class="text-center" data-translate-key="tm_col_follow_ups">Follow-ups</th>
                            <th class="text-center" data-translate-key="tm_col_visits">Visits</th>
                            <th class="text-center" data-translate-key="tm_col_sales">Sales</th>
                            <th class="text-center" data-translate-key="tm_col_conversion">Conversion</th>
                            <th class="text-center" data-translate-key="tm_col_overdue">Overdue</th>
                            <th style="min-width:150px" data-translate-key="tm_col_target">Monthly target</th>
                            <th style="min-width:120px" data-translate-key="tm_col_progress">Progress</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($team)): ?>
                            <tr><td colspan="10" class="text-center text-muted p-4" data-translate-key="tm_no_team">You have no employees in your team yet. Ask the company admin to add them.</td></tr>
                        <?php else: ?>
                            <?php foreach ($team as $m):
                                $uid = (int) $m['id'];
                                $rep = $report_by_user[$uid] ?? ['leads' => 0, 'calls' => 0, 'follow_ups' => 0, 'visits' => 0, 'sales' => 0];
                                $tg  = $targets_by_user[$uid] ?? ['target_leads' => 0, 'actual_leads' => 0];
                                $pct = $tg['target_leads'] > 0 ? min(100, (int) round($tg['actual_leads'] / $tg['target_leads'] * 100)) : 0;
                                $over = $overdue_by_user[$uid] ?? 0; ?>
                                <tr>
                                    <td class="fw-semibold"><?php echo htmlspecialchars($m['username']); ?></td>
                                    <td class="text-center"><?php echo (int) $rep['leads']; ?></td>
                                    <td class="text-center"><?php echo (int) $rep['calls']; ?></td>
                                    <td class="text-center"><?php echo (int) $rep['follow_ups']; ?></td>
                                    <td class="text-center"><?php echo (int) $rep['visits']; ?></td>
                                    <td class="text-center"><?php echo (int) $rep['sales']; ?></td>
                                    <td class="text-center"><?php echo tm_conversion((int) $rep['sales'], (int) $rep['leads']); ?></td>
                                    <td class="text-center"><?php echo $over > 0 ? '<span class="badge bg-danger">' . $over . '</span>' : '0'; ?></td>
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <input type="number" min="0" max="100000" class="form-control js-target-input" data-employee="<?php echo $uid; ?>" value="<?php echo (int) $tg['target_leads']; ?>">
                                            <button type="button" class="btn btn-outline-primary js-save-target" data-employee="<?php echo $uid; ?>" data-translate-key="btn_save">Save</button>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($tg['target_leads'] > 0): ?>
                                            <div class="progress" style="height:8px" role="progressbar" aria-valuenow="<?php echo $pct; ?>" aria-valuemin="0" aria-valuemax="100">
                                                <div class="progress-bar" style="width:<?php echo $pct; ?>%"></div>
                                            </div>
                                            <small class="text-muted"><?php echo (int) $tg['actual_leads'] . ' / ' . (int) $tg['target_leads']; ?></small>
                                        <?php else: ?>
                                            <small class="text-muted"><?php echo (int) $tg['actual_leads']; ?> / —</small>
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

    <!-- Needs attention: overdue follow-ups of my team -->
    <div class="card data-card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0" data-translate-key="tm_attention_title">Needs Attention</h5>
        </div>
        <?php if (empty($overdue_list)): ?>
            <div class="card-body text-center text-muted" data-translate-key="tm_attention_empty">No overdue follow-ups in your team.</div>
        <?php else: ?>
            <ul class="list-group list-group-flush">
                <?php foreach ($overdue_list as $o): ?>
                    <li class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <a href="client_timeline.php?id=<?php echo (int) $o['id']; ?>" class="fw-semibold text-decoration-none"><?php echo htmlspecialchars($o['client_name']); ?></a>
                            <small class="text-muted d-block"><?php echo htmlspecialchars($team_names[(int) $o['user_id']] ?? ''); ?></small>
                        </div>
                        <span class="badge bg-danger"><?php echo htmlspecialchars($o['event_date']); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <!-- Distribute + monitor leads -->
    <div class="card data-card mb-4">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h5 class="card-title mb-0" data-translate-key="tm_leads_title">Team Leads</h5>
            <div class="d-flex flex-wrap gap-2">
                <select id="leadOwnerFilter" class="form-select form-select-sm w-auto">
                    <option value="all" data-translate-key="tm_filter_all">All team leads</option>
                    <option value="<?php echo $viewer_id; ?>" data-translate-key="tm_filter_waiting">Waiting for assignment (mine)</option>
                    <?php foreach ($team as $m): ?>
                        <option value="<?php echo (int) $m['id']; ?>"><?php echo htmlspecialchars($m['username']); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="search" id="leadSearch" class="form-control form-control-sm w-auto" placeholder="Search leads..." data-translate-key="tm_search_ph">
            </div>
        </div>
        <?php if (!empty($team)): ?>
        <div class="card-body border-bottom">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <label class="mb-0 small text-muted" for="assignTo" data-translate-key="tm_assign_to">Assign to</label>
                <select id="assignTo" class="form-select form-select-sm w-auto">
                    <?php foreach ($assignees as $a): ?>
                        <option value="<?php echo (int) $a['id']; ?>"><?php echo htmlspecialchars($a['username']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" id="assignBtn" class="btn btn-sm btn-primary" data-translate-key="tm_assign_btn">Assign selected</button>
                <button type="button" id="distributeBtn" class="btn btn-sm btn-outline-primary" data-translate-key="tm_distribute_btn">Distribute evenly</button>
            </div>
            <small class="text-muted d-block mt-2" data-translate-key="tm_hint_distribute">"Distribute evenly" splits the selected leads equally between all your employees.</small>
        </div>
        <?php endif; ?>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="leadsTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width:36px"><input type="checkbox" class="form-check-input" id="selectAllLeads" aria-label="Select all"></th>
                            <th data-translate-key="tm_col_lead">Lead</th>
                            <th data-translate-key="tm_col_phone">Phone</th>
                            <th data-translate-key="tm_col_owner">Assigned to</th>
                            <th data-translate-key="tm_col_stage">Stage</th>
                            <th data-translate-key="tm_col_status">Status</th>
                            <th data-translate-key="tm_col_followup">Follow-up</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($all_leads)): ?>
                            <tr><td colspan="8" class="text-center text-muted p-4" data-translate-key="tm_no_leads">No leads yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($all_leads as $lead):
                                $stage = in_array($lead['pipeline_stage'] ?? '', array_keys($stage_keys), true) ? $lead['pipeline_stage'] : 'New Lead';
                                $follow = !empty($lead['event_date']) ? $lead['event_date'] . (!empty($lead['event_time']) ? ' ' . substr($lead['event_time'], 0, 5) : '') : '—'; ?>
                                <tr class="js-lead-row" data-owner="<?php echo (int) $lead['user_id']; ?>"
                                    data-search="<?php echo htmlspecialchars(mb_strtolower($lead['client_name'] . ' ' . $lead['phone_number'] . ' ' . ($lead['employee_name'] ?? ''))); ?>">
                                    <td><input type="checkbox" class="form-check-input js-lead-check" value="<?php echo (int) $lead['id']; ?>"></td>
                                    <td class="fw-semibold"><?php echo htmlspecialchars($lead['client_name']); ?></td>
                                    <td><?php echo htmlspecialchars($lead['phone_number']); ?></td>
                                    <td><?php echo htmlspecialchars($lead['employee_name'] ?? ''); ?></td>
                                    <td><span data-translate-key="<?php echo $stage_keys[$stage]; ?>"><?php echo htmlspecialchars($stage); ?></span></td>
                                    <td><?php echo T(!empty($lead['final_status']) ? $lead['final_status'] : 'Follow-up'); ?></td>
                                    <td><?php echo htmlspecialchars($follow); ?></td>
                                    <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="client_timeline.php?id=<?php echo (int) $lead['id']; ?>"><i class="fa-solid fa-eye"></i></a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const teamIds = <?php echo json_encode($team_ids); ?>;
    const period = <?php echo json_encode($period); ?>;
    const msg = document.getElementById('teamMsg');
    const t = (key, fb) => (window.tr ? window.tr(key, fb) : fb);

    function show(ok, key, fb) {
        msg.className = 'alert mb-4 ' + (ok ? 'alert-success' : 'alert-danger');
        msg.textContent = t(key, fb);
        msg.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    function post(url, params) {
        return fetch(url, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params.toString()
        }).then(function (r) { return r.json().catch(function () { return { success: false }; }); })
          .catch(function () { return { success: false }; });
    }
    function selectedIds() {
        return Array.from(document.querySelectorAll('.js-lead-check:checked')).map(function (c) { return c.value; });
    }
    function assignParams(ids, assignee) {
        const p = new URLSearchParams();
        ids.forEach(function (id) { p.append('client_ids[]', id); });
        p.append('assignee_id', assignee);
        return p;
    }
    // The endpoint accepts at most 200 leads per request, so send in chunks.
    function assignAll(ids, assignee) {
        const calls = [];
        for (let i = 0; i < ids.length; i += 150) {
            calls.push(post('../src/actions/handle_lead_assign.php', assignParams(ids.slice(i, i + 150), assignee)));
        }
        return Promise.all(calls);
    }
    function finish(results) {
        const ok = results.every(function (r) { return r.success && (!r.failed || !r.failed.length); });
        if (ok) { show(true, 'tm_msg_assigned', 'Leads assigned.'); setTimeout(function () { location.reload(); }, 700); }
        else if (results.some(function (r) { return r.success; })) { show(false, 'tm_msg_partial', 'Some leads could not be assigned.'); }
        else { show(false, 'tm_msg_denied', 'Not allowed or failed.'); }
    }

    // Monthly target (server re-checks that the employee is in my team).
    document.querySelectorAll('.js-save-target').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id = btn.dataset.employee;
            const input = document.querySelector('.js-target-input[data-employee="' + id + '"]');
            post('../src/actions/handle_target_save.php', new URLSearchParams({ employee_id: id, target_leads: input.value, period: period }))
                .then(function (res) {
                    if (res.success) { show(true, 'tm_msg_saved', 'Target saved.'); setTimeout(function () { location.reload(); }, 700); }
                    else { show(false, 'tm_msg_denied', 'Not allowed or failed.'); }
                });
        });
    });

    // Filters
    const ownerSel = document.getElementById('leadOwnerFilter');
    const search = document.getElementById('leadSearch');
    function applyFilter() {
        const owner = ownerSel.value;
        const q = search.value.trim().toLowerCase();
        document.querySelectorAll('.js-lead-row').forEach(function (row) {
            const okOwner = owner === 'all' || row.dataset.owner === owner;
            const okText = row.dataset.search.indexOf(q) !== -1;
            row.style.display = (okOwner && okText) ? '' : 'none';
        });
    }
    ownerSel.addEventListener('change', applyFilter);
    search.addEventListener('input', applyFilter);

    const selectAll = document.getElementById('selectAllLeads');
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.js-lead-row').forEach(function (row) {
                if (row.style.display !== 'none') row.querySelector('.js-lead-check').checked = selectAll.checked;
            });
        });
    }

    // Assign the selected leads to one person.
    const assignBtn = document.getElementById('assignBtn');
    if (assignBtn) {
        assignBtn.addEventListener('click', function () {
            const ids = selectedIds();
            if (!ids.length) { show(false, 'tm_msg_select_lead', 'Select at least one lead.'); return; }
            assignAll(ids, document.getElementById('assignTo').value).then(finish);
        });
    }

    // Round-robin the selected leads across every employee of my team.
    const distributeBtn = document.getElementById('distributeBtn');
    if (distributeBtn) {
        distributeBtn.addEventListener('click', function () {
            const ids = selectedIds();
            if (!ids.length) { show(false, 'tm_msg_select_lead', 'Select at least one lead.'); return; }
            if (!teamIds.length) { show(false, 'tm_msg_no_team', 'You have no employees in your team yet.'); return; }
            const buckets = {};
            ids.forEach(function (id, i) {
                const emp = teamIds[i % teamIds.length];
                (buckets[emp] = buckets[emp] || []).push(id);
            });
            Promise.all(Object.keys(buckets).map(function (emp) {
                return assignAll(buckets[emp], emp);
            })).then(function (groups) { finish([].concat.apply([], groups)); });
        });
    }
})();
</script>

<?php require_once __DIR__ . '/../src/templates/footer.php'; ?>
