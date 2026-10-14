<?php
require_once __DIR__ . '/../src/auth/auth_check.php';
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/installment_helper.php';

$company_id = $_SESSION['company_id'] ?? null;
$installments = get_installments($conn, $company_id);
$sold_properties = get_sold_properties($conn, $company_id);

$summary = ['paid' => 0, 'pending' => 0, 'overdue' => 0];
foreach ($installments as $inst) {
    $summary[$inst['computed_status']] = ($summary[$inst['computed_status']] ?? 0) + (float) $inst['amount'];
}

require_once __DIR__ . '/../src/templates/header.php';
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/installment_tracker.css">
<div class="container-fluid py-4 px-4">
    <div class="page-title-row mb-2">
        <h2 class="fw-bold mb-0"><i class="fa-solid fa-money-check-dollar me-2"></i><span data-translate-key="installment_tracker_title">Installment Tracker</span></h2>
    </div>
    <div class="page-action-center mb-4">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#installmentModal" onclick="openCreateInstallmentModal()" <?php echo empty($sold_properties) ? 'disabled' : ''; ?>>
            <i class="fa-solid fa-plus me-1"></i> <span data-translate-key="add_installment_btn">Add Installment</span>
        </button>
    </div>

    <?php if (empty($sold_properties)): ?>
        <div class="alert alert-warning" data-translate-key="it_no_sold_properties">No sold properties yet. Mark a property as "Sold" in Property Inventory to start tracking its installments.</div>
    <?php endif; ?>

    <!-- Summary cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card shadow-sm it-summary-card it-summary-paid">
                <div class="card-body">
                    <div class="it-summary-label" data-translate-key="it_summary_paid">Paid</div>
                    <div class="it-summary-value"><?php echo number_format($summary['paid'] ?? 0, 2); ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card shadow-sm it-summary-card it-summary-pending">
                <div class="card-body">
                    <div class="it-summary-label" data-translate-key="it_summary_pending">Pending</div>
                    <div class="it-summary-value"><?php echo number_format($summary['pending'] ?? 0, 2); ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card shadow-sm it-summary-card it-summary-overdue">
                <div class="card-body">
                    <div class="it-summary-label" data-translate-key="it_summary_overdue">Overdue</div>
                    <div class="it-summary-value"><?php echo number_format($summary['overdue'] ?? 0, 2); ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card shadow-sm it-summary-card it-summary-total">
                <div class="card-body">
                    <div class="it-summary-label" data-translate-key="it_summary_total">Total</div>
                    <div class="it-summary-value"><?php echo number_format(array_sum($summary), 2); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter -->
    <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
        <label class="form-label mb-0 fw-semibold" for="it_status_filter" data-translate-key="it_filter_status">Filter by status:</label>
        <select id="it_status_filter" class="form-select form-select-sm" style="max-width: 200px;">
            <option value="all" data-translate-key="it_filter_all">All</option>
            <option value="paid" data-translate-key="status_paid">Paid</option>
            <option value="pending" data-translate-key="status_pending">Pending</option>
            <option value="overdue" data-translate-key="status_overdue">Overdue</option>
        </select>
    </div>

    <div class="card shadow-sm installment-card">
        <div class="card-body">
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th data-translate-key="col_property">Property</th>
                            <th data-translate-key="col_due_date">Due Date</th>
                            <th data-translate-key="col_amount">Amount</th>
                            <th data-translate-key="col_installment_status">Status</th>
                            <th class="text-end" data-translate-key="col_actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="it_table_body">
                        <?php if (empty($installments)): ?>
                            <tr id="it_empty_row">
                                <td colspan="5" class="text-center text-muted py-4" data-translate-key="it_no_installments_yet">No installments yet.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($installments as $inst): ?>
                            <?php
                                $status = $inst['computed_status'];
                                $badge_class = 'bg-secondary';
                                $status_key = 'status_pending';
                                if ($status === 'paid') { $badge_class = 'bg-success'; $status_key = 'status_paid'; }
                                elseif ($status === 'overdue') { $badge_class = 'bg-danger'; $status_key = 'status_overdue'; }
                            ?>
                            <tr data-installment-status="<?php echo htmlspecialchars($status); ?>">
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($inst['project']); ?></div>
                                    <div class="text-muted small"><?php echo htmlspecialchars($inst['unit_type'] . ' — ' . $inst['developer']); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($inst['due_date']); ?></td>
                                <td><?php echo number_format((float) $inst['amount'], 2); ?></td>
                                <td>
                                    <span class="badge <?php echo $badge_class; ?> it-status-badge" data-translate-key="<?php echo $status_key; ?>"><?php echo ucfirst($status); ?></span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-outline-success it-toggle-paid-btn"
                                                data-id="<?php echo (int) $inst['id']; ?>"
                                                data-paid="<?php echo $status === 'paid' ? '1' : '0'; ?>"
                                                data-mark-paid-key="it_mark_paid" data-mark-unpaid-key="it_mark_unpaid"
                                                title="<?php echo $status === 'paid' ? 'Mark as unpaid' : 'Mark as paid'; ?>">
                                            <i class="fa-solid <?php echo $status === 'paid' ? 'fa-rotate-left' : 'fa-check'; ?>"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-primary"
                                                data-bs-toggle="modal" data-bs-target="#installmentModal"
                                                onclick='openEditInstallmentModal(<?php echo json_encode($inst); ?>)'>
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                        <a href="../src/actions/handle_installment_delete.php?id=<?php echo (int) $inst['id']; ?>"
                                           class="btn btn-sm btn-outline-danger installment-delete-link"
                                           data-confirm-key="confirm_delete_installment">
                                            <i class="fa-solid fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile card view -->
            <div class="installment-cards d-md-none" id="it_cards_container">
                <?php if (empty($installments)): ?>
                    <p class="text-center text-muted py-4 mb-0" id="it_empty_card" data-translate-key="it_no_installments_yet">No installments yet.</p>
                <?php endif; ?>
                <?php foreach ($installments as $inst): ?>
                    <?php
                        $status = $inst['computed_status'];
                        $badge_class = 'bg-secondary';
                        $status_key = 'status_pending';
                        if ($status === 'paid') { $badge_class = 'bg-success'; $status_key = 'status_paid'; }
                        elseif ($status === 'overdue') { $badge_class = 'bg-danger'; $status_key = 'status_overdue'; }
                    ?>
                    <div class="installment-mobile-card" data-installment-status="<?php echo htmlspecialchars($status); ?>">
                        <div class="installment-mobile-card-header">
                            <span class="installment-mobile-project"><?php echo htmlspecialchars($inst['project']); ?></span>
                            <span class="badge <?php echo $badge_class; ?> it-status-badge" data-translate-key="<?php echo $status_key; ?>"><?php echo ucfirst($status); ?></span>
                        </div>
                        <div class="installment-mobile-row"><span class="installment-mobile-label" data-translate-key="col_due_date">Due Date:</span> <span><?php echo htmlspecialchars($inst['due_date']); ?></span></div>
                        <div class="installment-mobile-row"><span class="installment-mobile-label" data-translate-key="col_amount">Amount:</span> <span><?php echo number_format((float) $inst['amount'], 2); ?></span></div>
                        <div class="installment-mobile-row"><span class="installment-mobile-label" data-translate-key="label_unit_type">Unit Type:</span> <span><?php echo htmlspecialchars($inst['unit_type']); ?></span></div>
                        <div class="installment-mobile-actions">
                            <button type="button" class="btn btn-sm btn-outline-success it-toggle-paid-btn"
                                    data-id="<?php echo (int) $inst['id']; ?>"
                                    data-paid="<?php echo $status === 'paid' ? '1' : '0'; ?>"
                                    data-mark-paid-key="it_mark_paid" data-mark-unpaid-key="it_mark_unpaid">
                                <i class="fa-solid <?php echo $status === 'paid' ? 'fa-rotate-left' : 'fa-check'; ?>"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="modal" data-bs-target="#installmentModal"
                                    onclick='openEditInstallmentModal(<?php echo json_encode($inst); ?>)'>
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <a href="../src/actions/handle_installment_delete.php?id=<?php echo (int) $inst['id']; ?>"
                               class="btn btn-sm btn-outline-danger installment-delete-link"
                               data-confirm-key="confirm_delete_installment">
                                <i class="fa-solid fa-trash"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add / Edit Installment Modal -->
<div class="modal fade" id="installmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="../src/actions/handle_installment_save.php" method="POST" id="installmentForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="installmentModalTitle" data-translate-key="installment_modal_add_title">Add Installment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="installment_id" id="installment_id" value="">
                    <div class="mb-3">
                        <label class="form-label" data-translate-key="field_linked_property">Linked Property (sold)</label>
                        <select name="property_id" id="installment_property_id" class="form-select" required>
                            <option value="" data-translate-key="cc_choose_property">-- Choose a property --</option>
                            <?php foreach ($sold_properties as $sp): ?>
                                <option value="<?php echo (int) $sp['id']; ?>">
                                    <?php echo htmlspecialchars($sp['project'] . ' — ' . $sp['unit_type'] . ' (' . $sp['developer'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" data-translate-key="col_due_date">Due Date</label>
                        <input type="date" name="due_date" id="installment_due_date" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" data-translate-key="col_amount">Amount</label>
                        <input type="number" step="0.01" min="0.01" name="amount" id="installment_amount" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" data-translate-key="field_installment_note">Note (optional)</label>
                        <input type="text" name="note" id="installment_note" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" data-translate-key="btn_cancel">Cancel</button>
                    <button type="submit" class="btn btn-primary" data-translate-key="btn_save">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="js/installment_tracker.js"></script>

<?php require_once __DIR__ . '/../src/templates/footer.php'; ?>
