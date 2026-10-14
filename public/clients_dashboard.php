<?php
require_once __DIR__ . '/../src/auth/auth_check.php';
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/access_helper.php';
$user_id = $_SESSION['user_id'];
$is_manager = access_can_see_team();
$company_id = $_SESSION['company_id'] ?? null;
require_once __DIR__ . '/../src/client_filter_helper.php';
// Advanced filters (Employee / Status / Date) - applied in SQL, inside the
// caller's role scope (admin: company, manager: own team, employee: own data).
$cf = client_filters_from_request($_GET);
$clients = list_filtered_clients($conn, $cf, $is_manager);
$cf_stats = get_client_filter_stats($conn, $cf);
$cf_employees = client_filter_employees($conn);
$cf_status_options = client_status_options();
$cf_link = function (array $override) use ($cf) {
    $q = array_filter(array_merge($cf, $override), function ($v) { return $v !== '' && $v !== 0; });
    return 'clients_dashboard.php' . ($q ? '?' . http_build_query($q) : '');
};

// Build distinct option lists for the filter dropdowns (Status / Employee)
// directly from the already-fetched result set — no extra queries needed.
$distinct_statuses = [];
$distinct_employees = [];
$distinct_locations = [];
$distinct_unit_types = [];
$distinct_payment_methods = [];
foreach ($clients as $c) {
    $st = !empty($c['final_status']) ? $c['final_status'] : 'Follow-up';
    if (!in_array($st, $distinct_statuses, true)) {
        $distinct_statuses[] = $st;
    }
    if ($is_manager && !empty($c['employee_name']) && !in_array($c['employee_name'], $distinct_employees, true)) {
        $distinct_employees[] = $c['employee_name'];
    }
    if (!empty($c['project_location']) && !in_array($c['project_location'], $distinct_locations, true)) {
        $distinct_locations[] = $c['project_location'];
    }
    if (!empty($c['the_role']) && !in_array($c['the_role'], $distinct_unit_types, true)) {
        $distinct_unit_types[] = $c['the_role'];
    }
    if (!empty($c['installment']) && !in_array($c['installment'], $distinct_payment_methods, true)) {
        $distinct_payment_methods[] = $c['installment'];
    }
}
sort($distinct_statuses);
sort($distinct_employees);
sort($distinct_locations);
sort($distinct_unit_types);
sort($distinct_payment_methods);


require_once __DIR__ . '/../src/templates/header.php';
?>
<link rel="stylesheet" href="css/client_dashboard.css">

<div class="container-fluid">
    <div class="page-header">
        <h1 class="page-title" data-translate-key="clients_dash_title">Clients Dashboard</h1>
        <p class="page-subtitle" data-translate-key="clients_dash_subtitle">View, update, and contact all your clients.</p>
    </div>

    <div class="d-flex justify-content-end align-items-center mb-3">
        <a href="client_registration.php" class="btn btn-primary btn-add-client">
            <i class="fa-solid fa-plus"></i><span class="d-none d-md-inline ms-2" data-translate-key="add_new_client">Add New Client</span>
        </a>
    </div>

    <link rel="stylesheet" href="css/client_filters.css">
    <form method="GET" action="clients_dashboard.php" class="filters-bar cf-advanced" id="advancedFilters">
        <div class="filters-row">
            <?php if ($is_manager): ?>
            <div class="filter-field filter-field-grow">
                <label class="filter-label" for="cfEmployee" data-translate-key="cf_employee">Employee</label>
                <select class="form-select filter-input" id="cfEmployee" name="employee_id">
                    <option value="" data-translate-key="cf_all_employees">All employees</option>
                    <?php foreach ($cf_employees as $emp): ?>
                        <option value="<?php echo (int) $emp['id']; ?>" <?php echo ($cf['employee_id'] === (int) $emp['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($emp['username']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="filter-field filter-field-grow">
                <label class="filter-label" for="cfStatus" data-translate-key="cf_status">Lead Status</label>
                <select class="form-select filter-input" id="cfStatus" name="status">
                    <option value="" data-translate-key="cf_all_statuses">All statuses</option>
                    <?php foreach ($cf_status_options as $key => $opt): ?>
                        <option value="<?php echo htmlspecialchars($key); ?>" data-translate-key="<?php echo $opt[0]; ?>" <?php echo ($cf['status'] === $key) ? 'selected' : ''; ?>><?php echo $opt[1]; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label class="filter-label" for="cfFrom" data-translate-key="cf_from">From</label>
                <input type="date" class="form-control filter-input" id="cfFrom" name="from" value="<?php echo htmlspecialchars($cf['from']); ?>">
            </div>
            <div class="filter-field">
                <label class="filter-label" for="cfTo" data-translate-key="cf_to">To</label>
                <input type="date" class="form-control filter-input" id="cfTo" name="to" value="<?php echo htmlspecialchars($cf['to']); ?>">
            </div>
            <div class="filter-field filter-field-actions">
                <label class="filter-label">&nbsp;</label>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-filter me-1"></i><span data-translate-key="cf_apply">Apply</span>
                    </button>
                    <a href="clients_dashboard.php" class="btn btn-outline-secondary">
                        <i class="fa-solid fa-rotate-left me-1"></i><span data-translate-key="cf_reset">Reset</span>
                    </a>
                </div>
            </div>
        </div>
        <div class="cf-stats">
            <a class="cf-chip cf-chip-total <?php echo $cf['status'] === '' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($cf_link(['status' => ''])); ?>">
                <span data-translate-key="cf_total">Total</span> <strong><?php echo $cf_stats['total']; ?></strong>
            </a>
            <?php foreach ($cf_status_options as $key => $opt): ?>
                <a class="cf-chip <?php echo $cf['status'] === $key ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($cf_link(['status' => $key])); ?>">
                    <span data-translate-key="<?php echo $opt[0]; ?>"><?php echo $opt[1]; ?></span> <strong><?php echo $cf_stats['by_status'][$key]; ?></strong>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="filters-summary">
            <span data-translate-key="cf_showing">Showing</span> <strong><?php echo count($clients); ?></strong>
            <span data-translate-key="cf_of">of</span> <?php echo $cf_stats['total']; ?>
        </div>
    </form>

    <div class="filters-bar">
        <div class="filters-row">
            <div class="filter-field filter-field-grow">
                <label class="filter-label" data-translate-key="filter_client_name">Client Name</label>
                <input type="text" class="form-control filter-input" id="filterClientName" data-translate-key="filter_client_name_ph" placeholder="Search by name...">
            </div>
            <div class="filter-field filter-field-grow">
                <label class="filter-label" data-translate-key="filter_phone">Phone</label>
                <input type="text" class="form-control filter-input" id="filterPhone" data-translate-key="filter_phone_ph" placeholder="Search by phone...">
            </div>
            <div class="filter-field">
                <label class="filter-label" data-translate-key="filter_location">Location</label>
                <select class="form-select filter-input" id="filterLocation">
                    <option value="" data-translate-key="filter_all">All</option>
                    <?php foreach ($distinct_locations as $loc): ?>
                        <option value="<?php echo htmlspecialchars($loc); ?>"><?php echo htmlspecialchars($loc); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field filter-field-grow">
                <label class="filter-label" data-translate-key="filter_project">Project</label>
                <input type="text" class="form-control filter-input" id="filterProject" data-translate-key="filter_project_ph" placeholder="Search by project...">
            </div>
            <div class="filter-field">
                <label class="filter-label" data-translate-key="filter_status">Status</label>
                <select class="form-select filter-input" id="filterStatus">
                    <option value="" data-translate-key="filter_all">All</option>
                    <?php foreach ($distinct_statuses as $st): ?>
                        <option value="<?php echo htmlspecialchars($st); ?>"><?php echo htmlspecialchars($st); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label class="filter-label" data-translate-key="filter_date">Follow-up Date</label>
                <input type="date" class="form-control filter-input" id="filterDate">
            </div>
            <div class="filter-field">
                <label class="filter-label" data-translate-key="filter_reg_date">Registration Date</label>
                <input type="date" class="form-control filter-input" id="filterRegDate">
            </div>
            <div class="filter-field">
                <label class="filter-label" data-translate-key="filter_unit_type">Unit Type</label>
                <select class="form-select filter-input" id="filterUnitType">
                    <option value="" data-translate-key="filter_all">All</option>
                    <?php foreach ($distinct_unit_types as $ut): ?>
                        <option value="<?php echo htmlspecialchars($ut); ?>"><?php echo htmlspecialchars($ut); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label class="filter-label" data-translate-key="filter_payment_method">Payment Method</label>
                <select class="form-select filter-input" id="filterPaymentMethod">
                    <option value="" data-translate-key="filter_all">All</option>
                    <?php foreach ($distinct_payment_methods as $pm): ?>
                        <option value="<?php echo htmlspecialchars($pm); ?>"><?php echo htmlspecialchars($pm); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field filter-field-budget">
                <label class="filter-label" data-translate-key="filter_budget">Budget (Unit Price)</label>
                <div class="filter-budget-range">
                    <input type="number" class="form-control filter-input" id="filterBudgetMin" data-translate-key="filter_min" placeholder="Min">
                    <span class="filter-budget-sep">&ndash;</span>
                    <input type="number" class="form-control filter-input" id="filterBudgetMax" data-translate-key="filter_max" placeholder="Max">
                </div>
            </div>
            <div class="filter-field">
                <label class="filter-label" data-translate-key="filter_last_contact">Last Contact</label>
                <select class="form-select filter-input" id="filterLastContact">
                    <option value="" data-translate-key="filter_all">All</option>
                    <option value="7" data-translate-key="filter_last_7">Last 7 days</option>
                    <option value="30" data-translate-key="filter_last_30">Last 30 days</option>
                    <option value="90" data-translate-key="filter_last_90">Last 90 days</option>
                    <option value="90+" data-translate-key="filter_over_90">Over 90 days</option>
                </select>
            </div>
            <div class="filter-field filter-field-actions">
                <label class="filter-label">&nbsp;</label>
                <button type="button" class="btn btn-outline-secondary filter-clear-btn" id="filterClearBtn">
                    <i class="fa-solid fa-xmark me-1"></i><span data-translate-key="filter_clear">Clear</span>
                </button>
            </div>
        </div>
        <div class="filters-summary">
            <span id="filterResultsCount"></span>
        </div>
    </div>

    <div class="row g-4">
        <?php if (empty($clients)): ?>
            <div class="col-12 text-center p-5"><p class="text-muted" data-translate-key="no_clients_found">No clients found.</p></div>
        <?php else: ?>
            <?php foreach ($clients as $client): ?>
               
                <div class="col-12 col-md-6 col-xl-4 client-card-wrapper"
                     data-name="<?php echo htmlspecialchars(mb_strtolower($client['client_name'] ?? '')); ?>"
                     data-phone="<?php echo htmlspecialchars(strtolower($client['phone_number'] ?? '')); ?>"
                     data-location="<?php echo htmlspecialchars($client['project_location'] ?? ''); ?>"
                     data-project="<?php echo htmlspecialchars(mb_strtolower($client['project_name'] ?? '')); ?>"
                     data-status="<?php echo htmlspecialchars(!empty($client['final_status']) ? $client['final_status'] : 'Follow-up'); ?>"
                     data-employee="<?php echo htmlspecialchars($client['employee_name'] ?? ''); ?>"
                     data-event-date="<?php echo htmlspecialchars($client['event_date'] ?? ''); ?>"
                     data-budget="<?php echo htmlspecialchars($client['unit_price'] ?? ''); ?>"
                     data-created-at="<?php echo htmlspecialchars($client['created_at'] ?? ''); ?>"
                     data-reg-date="<?php echo htmlspecialchars($client['created_at'] ? date('Y-m-d', strtotime($client['created_at'])) : ''); ?>"
                     data-unit-type="<?php echo htmlspecialchars($client['the_role'] ?? ''); ?>"
                     data-payment-method="<?php echo htmlspecialchars($client['installment'] ?? ''); ?>">
                    <?php
                        $status = $client['final_status'] ?? 'Follow-up';
                        $badge_class = 'bg-secondary';
                        if ($status == 'Interested') $badge_class = 'bg-success';
                        if ($status == 'Follow-up') $badge_class = 'bg-warning text-dark';
                        if ($status == 'Not Interested') $badge_class = 'bg-danger';
                        if ($status == 'Contracted') $badge_class = 'bg-primary';
                        $modalId = 'clientDetailsModal' . (int)$client['id'];
                    ?>
                    <div class="card client-card client-card-compact h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="client-card-main">
                                    <h5 class="card-title mb-0 client-name"><?php echo htmlspecialchars($client['client_name']); ?></h5>
                                    <small class="text-muted client-phone"><i class="fa-solid fa-phone me-1"></i><?php echo htmlspecialchars($client['phone_number']); ?></small>
                                </div>
                                <div class="badge rounded-pill <?php echo $badge_class; ?> status-badge">
                                    <?php echo T($status); ?>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="button" class="btn btn-sm btn-view-details" data-bs-toggle="modal" data-bs-target="#<?php echo $modalId; ?>">
                                <i class="fa-solid fa-eye me-1"></i> <span data-translate-key="view_details_btn">View Details</span>
                            </button>
                            <a href="client_registration.php?id=<?php echo $client['id']; ?>" class="btn btn-sm btn-update">
                                <i class="fa-solid fa-pen me-1"></i> <span data-translate-key="update_btn">Update</span>
                            </a>
                            <button type="button" class="btn btn-sm btn-whatsapp whatsapp-trigger-btn" data-whatsapp-target="#<?php echo whatsapp_modal_id($client['id']); ?>">
                                <i class="fa-brands fa-whatsapp me-1"></i> <span data-translate-key="whatsapp_btn">WhatsApp</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Details Modal (all previous card information, unchanged) -->
                <div class="modal fade client-details-modal" id="<?php echo $modalId; ?>" tabindex="-1" aria-labelledby="<?php echo $modalId; ?>Label" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <div>
                                    <h5 class="modal-title mb-0" id="<?php echo $modalId; ?>Label"><?php echo htmlspecialchars($client['client_name']); ?></h5>
                                    <small class="text-muted"><?php echo htmlspecialchars($client['phone_number']); ?></small>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="badge rounded-pill <?php echo $badge_class; ?> status-badge">
                                        <?php echo T($status); ?>
                                    </div>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                            </div>
                            <div class="modal-body">
                                <div class="client-data-section">
                                    <h6 data-translate-key="project_details">Project Details</h6>
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item"><strong data-translate-key="project_location">Location:</strong> <?php echo T($client['project_location']); ?></li>
                                        <li class="list-group-item"><strong data-translate-key="project_name">Project Name:</strong> <span><?php echo htmlspecialchars($client['project_name'] ?? 'N/A'); ?></span></li>
                                        <li class="list-group-item"><strong data-translate-key="unit_area">Unit Area:</strong> <span><?php echo htmlspecialchars($client['unit_area'] ?? 'N/A'); ?> m²</span></li>
                                        <li class="list-group-item"><strong data-translate-key="the_role">Role:</strong> <?php echo T($client['the_role']); ?></li>
                                        <li class="list-group-item"><strong data-translate-key="receiving">Receiving:</strong> <?php echo T($client['receiving']); ?></li>
                                    </ul>
                                </div>
                                <div class="client-data-section">
                                    <h6 data-translate-key="financials">Financials</h6>
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item"><strong data-translate-key="unit_price">Unit Price:</strong> <span><?php echo number_format($client['unit_price'] ?? 0, 2); ?></span></li>
                                        <li class="list-group-item"><strong data-translate-key="down_payment">Down Payment:</strong> <span><?php echo number_format($client['down_payment'] ?? 0, 2); ?></span></li>
                                        <li class="list-group-item"><strong data-translate-key="installment">Installment:</strong> <?php echo T($client['installment']); ?></li>
                                        <li class="list-group-item"><strong data-translate-key="installment_amount">Amount:</strong> <span><?php echo number_format($client['installment_amount'] ?? 0, 2); ?></span></li>
                                    </ul>
                                </div>
                                <div class="client-data-section">
                                    <h6 data-translate-key="communication">Communication</h6>
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item"><strong data-translate-key="visit">Visit Type:</strong> <?php echo T($client['visit']); ?></li>
                                        <li class="list-group-item"><strong data-translate-key="call_outcome">Call Outcome:</strong> <?php echo T($client['call_outcome']); ?></li>
                                        <li class="list-group-item"><strong data-translate-key="visit_outcome">Visit Outcome:</strong> <?php echo T($client['visit_outcome']); ?></li>
                                    </ul>
                                </div>
                                <div class="client-data-section">
                                    <h6 data-translate-key="calendar_event">Calendar Event</h6>
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item"><strong data-translate-key="event_title_cal">Title:</strong> <span><?php echo htmlspecialchars($client['event_title'] ?? 'N/A'); ?></span></li>
                                        <li class="list-group-item"><strong data-translate-key="event_date">Date:</strong> <span><?php echo htmlspecialchars($client['event_date'] ?? 'N/A'); ?></span></li>
                                        <li class="list-group-item"><strong data-translate-key="event_time">Time:</strong> <span><?php echo htmlspecialchars($client['event_time'] ?? 'N/A'); ?></span></li>
                                    </ul>
                                </div>
                                <div class="client-data-section">
                                    <h6 data-translate-key="note">Note</h6>
                                    <p class="client-note">
                                        <?php echo !empty($client['note']) ? nl2br(htmlspecialchars($client['note'])) : '<span class="text-muted">No notes added.</span>'; ?>
                                    </p>
                                </div>
                                <?php if ($is_manager): ?>
                                    <div class="client-data-section">
                                        <h6 data-translate-key="added_by">Added By</h6>
                                        <ul class="list-group list-group-flush">
                                            <li class="list-group-item"><span><?php echo htmlspecialchars($client['employee_name']); ?></span></li>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="modal-footer">
                                <a href="client_timeline.php?id=<?php echo $client['id']; ?>" class="btn btn-sm btn-timeline">
                                    <i class="fa-solid fa-timeline me-1"></i> <span data-translate-key="timeline_btn">Timeline</span>
                                </a>
                                <a href="client_registration.php?id=<?php echo $client['id']; ?>" class="btn btn-sm btn-update">
                                    <i class="fa-solid fa-pen me-1"></i> <span data-translate-key="update_btn">Update</span>
                                </a>
                                <button type="button" class="btn btn-sm btn-whatsapp whatsapp-trigger-btn" data-whatsapp-target="#<?php echo whatsapp_modal_id($client['id']); ?>">
                                    <i class="fa-brands fa-whatsapp me-1"></i> <span data-translate-key="whatsapp_btn">WhatsApp</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <?php echo render_whatsapp_modal($client); ?>
            <?php endforeach; ?>
        <?php endif; ?>
       
        <div id="no-results-message" class="col-12 text-center p-5" style="display: none;">
            <p class="text-muted fs-4" data-translate-key="no_clients_match_search">No clients match your search.</p>
        </div>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function () {
    const clientCards = document.querySelectorAll('.client-card-wrapper');
    const noResultsMessage = document.getElementById('no-results-message');
    const resultsCountEl = document.getElementById('filterResultsCount');

    const fields = {
        name: document.getElementById('filterClientName'),
        phone: document.getElementById('filterPhone'),
        location: document.getElementById('filterLocation'),
        project: document.getElementById('filterProject'),
        status: document.getElementById('filterStatus'),
        employee: document.getElementById('filterEmployee'), // may be null for non-managers
        date: document.getElementById('filterDate'),
        regDate: document.getElementById('filterRegDate'),
        unitType: document.getElementById('filterUnitType'),
        paymentMethod: document.getElementById('filterPaymentMethod'),
        budgetMin: document.getElementById('filterBudgetMin'),
        budgetMax: document.getElementById('filterBudgetMax'),
        lastContact: document.getElementById('filterLastContact'),
    };
    const clearBtn = document.getElementById('filterClearBtn');

    if (clientCards.length === 0) {
        return;
    }

    function msPerDay() { return 1000 * 60 * 60 * 24; }

    function matchesLastContact(createdAtStr, rangeValue) {
        if (!rangeValue) return true;
        if (!createdAtStr) return false;
        const created = new Date(createdAtStr.replace(' ', 'T'));
        if (isNaN(created.getTime())) return false;
        const daysAgo = (Date.now() - created.getTime()) / msPerDay();

        if (rangeValue === '7') return daysAgo <= 7;
        if (rangeValue === '30') return daysAgo <= 30;
        if (rangeValue === '90') return daysAgo <= 90;
        if (rangeValue === '90+') return daysAgo > 90;
        return true;
    }

    function applyFilters() {
        const nameVal = (fields.name.value || '').toLowerCase().trim();
        const phoneVal = (fields.phone.value || '').toLowerCase().trim();
        const locationVal = fields.location.value || '';
        const projectVal = (fields.project.value || '').toLowerCase().trim();
        const statusVal = fields.status.value || '';
        const employeeVal = fields.employee ? (fields.employee.value || '') : '';
        const dateVal = fields.date.value || '';
        const regDateVal = fields.regDate.value || '';
        const unitTypeVal = fields.unitType.value || '';
        const paymentMethodVal = fields.paymentMethod.value || '';
        const budgetMinVal = fields.budgetMin.value !== '' ? parseFloat(fields.budgetMin.value) : null;
        const budgetMaxVal = fields.budgetMax.value !== '' ? parseFloat(fields.budgetMax.value) : null;
        const lastContactVal = fields.lastContact.value || '';

        let visibleCount = 0;

        clientCards.forEach(function (card) {
            const d = card.dataset;
            let visible = true;

            if (visible && nameVal && !d.name.includes(nameVal)) visible = false;
            if (visible && phoneVal && !d.phone.includes(phoneVal)) visible = false;
            if (visible && locationVal && d.location !== locationVal) visible = false;
            if (visible && projectVal && !d.project.includes(projectVal)) visible = false;
            if (visible && statusVal && d.status !== statusVal) visible = false;
            if (visible && employeeVal && d.employee !== employeeVal) visible = false;
            if (visible && dateVal && d.eventDate !== dateVal) visible = false;
            if (visible && regDateVal && d.regDate !== regDateVal) visible = false;
            if (visible && unitTypeVal && d.unitType !== unitTypeVal) visible = false;
            if (visible && paymentMethodVal && d.paymentMethod !== paymentMethodVal) visible = false;

            if (visible && (budgetMinVal !== null || budgetMaxVal !== null)) {
                const budget = d.budget !== '' ? parseFloat(d.budget) : null;
                if (budget === null || isNaN(budget)) {
                    visible = false;
                } else {
                    if (budgetMinVal !== null && budget < budgetMinVal) visible = false;
                    if (budgetMaxVal !== null && budget > budgetMaxVal) visible = false;
                }
            }

            if (visible && !matchesLastContact(d.createdAt, lastContactVal)) visible = false;

            card.style.display = visible ? '' : 'none';
            if (visible) visibleCount++;
        });

        if (noResultsMessage) {
            noResultsMessage.style.display = visibleCount === 0 ? 'block' : 'none';
        }
        if (resultsCountEl) {
            resultsCountEl.textContent = visibleCount + ' / ' + clientCards.length;
        }
    }

    // Every filter updates instantly and they all combine (AND logic).
    Object.values(fields).forEach(function (el) {
        if (!el) return;
        const evt = (el.tagName === 'SELECT' || el.type === 'date') ? 'change' : 'input';
        el.addEventListener(evt, applyFilters);
    });

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            Object.values(fields).forEach(function (el) {
                if (!el) return;
                el.value = '';
            });
            applyFilters();
        });
    }

    applyFilters();
});
</script>

<?php

require_once __DIR__ . '/../src/templates/footer.php';
?>