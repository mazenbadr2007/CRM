<?php
require_once __DIR__ . '/../src/auth/auth_check.php';
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/property_helper.php';

$company_id = $_SESSION['company_id'] ?? null;
$properties = get_properties($conn, $company_id);

require_once __DIR__ . '/../src/templates/header.php';
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/commission_calculator.css">
<div class="container-fluid py-4 px-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h2 class="fw-bold mb-0"><i class="fa-solid fa-calculator me-2"></i><span data-translate-key="commission_calculator_title">Commission Calculator</span></h2>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card shadow-sm commission-card h-100">
                <div class="card-body">
                    <label class="form-label fw-semibold" for="cc_property_select" data-translate-key="cc_select_property">Select Property</label>
                    <select id="cc_property_select" class="form-select mb-3">
                        <option value="" data-translate-key="cc_choose_property">-- Choose a property --</option>
                        <?php foreach ($properties as $p): ?>
                            <option
                                value="<?php echo (int) $p['id']; ?>"
                                data-price="<?php echo htmlspecialchars($p['price'] ?? ''); ?>"
                                data-company-type="<?php echo htmlspecialchars($p['company_commission_type'] ?? 'percent'); ?>"
                                data-company-value="<?php echo htmlspecialchars($p['company_commission_value'] ?? ''); ?>"
                                data-sales-type="<?php echo htmlspecialchars($p['sales_commission_type'] ?? 'percent'); ?>"
                                data-sales-value="<?php echo htmlspecialchars($p['sales_commission_value'] ?? ''); ?>"
                            >
                                <?php echo htmlspecialchars($p['project'] . ' — ' . $p['unit_type'] . ' (' . $p['developer'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <?php if (empty($properties)): ?>
                        <p class="text-muted small mb-3" data-translate-key="no_properties_yet">No properties yet.</p>
                    <?php endif; ?>

                    <div id="cc_no_selection" class="text-center text-muted py-4">
                        <i class="fa-solid fa-house-circle-check fa-2x mb-2 d-block"></i>
                        <span data-translate-key="cc_no_selection_text">Select a property to calculate its commissions.</span>
                    </div>

                    <div id="cc_details" class="d-none">
                        <div class="cc-info-row">
                            <span class="cc-info-label" data-translate-key="label_price">Price:</span>
                            <span class="cc-info-value" id="cc_price_display">—</span>
                        </div>
                        <div class="cc-info-row">
                            <span class="cc-info-label" data-translate-key="field_company_commission">Company Commission</span>
                            <span class="cc-info-value" id="cc_company_rule_display">—</span>
                        </div>
                        <div class="cc-info-row">
                            <span class="cc-info-label" data-translate-key="field_sales_commission">Sales Commission</span>
                            <span class="cc-info-value" id="cc_sales_rule_display">—</span>
                        </div>

                        <hr>

                        <label class="form-label fw-semibold" for="cc_override_price" data-translate-key="cc_override_price_label">Sale Price (optional override)</label>
                        <input type="number" step="0.01" min="0" id="cc_override_price" class="form-control mb-1" data-translate-key="cc_override_price_ph" placeholder="Uses property price if left blank">
                        <p class="text-muted small" data-translate-key="cc_override_price_hint">Leave blank to use the property's listed price.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="row g-4 h-100">
                <div class="col-sm-6">
                    <div class="card shadow-sm commission-result-card h-100">
                        <div class="card-body text-center d-flex flex-column justify-content-center">
                            <div class="commission-result-icon company-icon">
                                <i class="fa-solid fa-building-columns"></i>
                            </div>
                            <div class="commission-result-label" data-translate-key="cc_company_commission_result">Company Commission</div>
                            <div class="commission-result-value" id="cc_company_result">0.00</div>
                            <div class="commission-result-sub" id="cc_company_result_sub">—</div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="card shadow-sm commission-result-card h-100">
                        <div class="card-body text-center d-flex flex-column justify-content-center">
                            <div class="commission-result-icon sales-icon">
                                <i class="fa-solid fa-user-tie"></i>
                            </div>
                            <div class="commission-result-label" data-translate-key="cc_sales_commission_result">Sales Commission</div>
                            <div class="commission-result-value" id="cc_sales_result">0.00</div>
                            <div class="commission-result-sub" id="cc_sales_result_sub">—</div>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card shadow-sm commission-summary-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <span class="fw-semibold" data-translate-key="cc_total_commission">Total Commission</span>
                                <span class="commission-total-value" id="cc_total_result">0.00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="js/commission_calculator.js"></script>

<?php require_once __DIR__ . '/../src/templates/footer.php'; ?>
