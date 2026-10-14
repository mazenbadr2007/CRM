<?php
require_once __DIR__ . '/../src/auth/auth_check.php';
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/property_helper.php';

$company_id = $_SESSION['company_id'] ?? null;
$properties = get_properties($conn, $company_id);

require_once __DIR__ . '/../src/templates/header.php';
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/property_inventory.css">
<div class="container-fluid py-4 px-4">
    <div class="page-title-row mb-2">
        <h2 class="fw-bold mb-0"><i class="fa-solid fa-building me-2"></i><span data-translate-key="property_inventory_title">Property Inventory</span></h2>
    </div>
    <div class="page-action-center mb-4">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#propertyModal" onclick="openCreateModal()">
            <i class="fa-solid fa-plus me-1"></i> <span data-translate-key="add_property_btn">Add Property</span>
        </button>
    </div>

    <div class="card shadow-sm property-card">
        <div class="card-body">
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th data-translate-key="col_developer">Developer</th>
                            <th data-translate-key="col_project">Project</th>
                            <th data-translate-key="col_unit_type">Unit Type</th>
                            <th data-translate-key="col_area">Area</th>
                            <th data-translate-key="col_bedrooms">Bedrooms</th>
                            <th data-translate-key="col_price">Price</th>
                            <th data-translate-key="col_down_payment">Down Payment</th>
                            <th data-translate-key="col_installments">Installments</th>
                            <th data-translate-key="col_status">Status</th>
                            <th class="text-end" data-translate-key="col_actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($properties)): ?>
                            <tr>
                                <td colspan="10" class="text-center text-muted py-4" data-translate-key="no_properties_yet">No properties yet.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($properties as $p): ?>
                            <?php
                                $status = strtolower($p['status']);
                                $badge_class = 'bg-success';
                                $status_key = 'status_available';
                                if ($status === 'sold') {
                                    $badge_class = 'bg-danger';
                                    $status_key = 'status_sold';
                                } elseif ($status === 'reserved') {
                                    $badge_class = 'bg-warning text-dark';
                                    $status_key = 'status_reserved';
                                }
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($p['developer']); ?></td>
                                <td><?php echo htmlspecialchars($p['project']); ?></td>
                                <td><?php echo htmlspecialchars($p['unit_type']); ?></td>
                                <td><?php echo $p['area'] !== null ? htmlspecialchars($p['area']) . ' m²' : '—'; ?></td>
                                <td><?php echo $p['bedrooms'] !== null ? (int) $p['bedrooms'] : '—'; ?></td>
                                <td><?php echo $p['price'] !== null ? number_format((float) $p['price'], 2) : '—'; ?></td>
                                <td><?php echo $p['down_payment'] !== null ? number_format((float) $p['down_payment'], 2) : '—'; ?></td>
                                <td><?php echo htmlspecialchars($p['installments'] ?: '—'); ?></td>
                                <td><span class="badge <?php echo $badge_class; ?>" data-translate-key="<?php echo $status_key; ?>"><?php echo htmlspecialchars(ucfirst($p['status'])); ?></span></td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-outline-primary"
                                                data-bs-toggle="modal" data-bs-target="#propertyModal"
                                                onclick='openEditModal(<?php echo json_encode($p); ?>)'>
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                        <a href="../src/actions/handle_property_delete.php?id=<?php echo (int) $p['id']; ?>"
                                           class="btn btn-sm btn-outline-danger property-delete-link"
                                           data-confirm-key="confirm_delete_property">
                                            <i class="fa-solid fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile card view (visible below md breakpoint) -->
            <div class="property-cards d-md-none">
                <?php if (empty($properties)): ?>
                    <p class="text-center text-muted py-4 mb-0" data-translate-key="no_properties_yet">No properties yet.</p>
                <?php endif; ?>
                <?php foreach ($properties as $p): ?>
                    <?php
                        $status = strtolower($p['status']);
                        $badge_class = 'bg-success';
                        $status_key = 'status_available';
                        if ($status === 'sold') {
                            $badge_class = 'bg-danger';
                            $status_key = 'status_sold';
                        } elseif ($status === 'reserved') {
                            $badge_class = 'bg-warning text-dark';
                            $status_key = 'status_reserved';
                        }
                    ?>
                    <div class="property-mobile-card">
                        <div class="property-mobile-card-header">
                            <span class="property-mobile-project"><?php echo htmlspecialchars($p['project']); ?></span>
                            <span class="badge <?php echo $badge_class; ?>" data-translate-key="<?php echo $status_key; ?>"><?php echo htmlspecialchars(ucfirst($p['status'])); ?></span>
                        </div>
                        <div class="property-mobile-row"><span class="property-mobile-label" data-translate-key="label_developer">Developer:</span> <span><?php echo htmlspecialchars($p['developer']); ?></span></div>
                        <div class="property-mobile-row"><span class="property-mobile-label" data-translate-key="label_unit_type">Unit Type:</span> <span><?php echo htmlspecialchars($p['unit_type']); ?></span></div>
                        <div class="property-mobile-row"><span class="property-mobile-label" data-translate-key="label_area">Area:</span> <span><?php echo $p['area'] !== null ? htmlspecialchars($p['area']) . ' m²' : '—'; ?></span></div>
                        <div class="property-mobile-row"><span class="property-mobile-label" data-translate-key="label_bedrooms">Bedrooms:</span> <span><?php echo $p['bedrooms'] !== null ? (int) $p['bedrooms'] : '—'; ?></span></div>
                        <div class="property-mobile-row"><span class="property-mobile-label" data-translate-key="label_price">Price:</span> <span><?php echo $p['price'] !== null ? number_format((float) $p['price'], 2) : '—'; ?></span></div>
                        <div class="property-mobile-row"><span class="property-mobile-label" data-translate-key="label_down_payment">Down Payment:</span> <span><?php echo $p['down_payment'] !== null ? number_format((float) $p['down_payment'], 2) : '—'; ?></span></div>
                        <div class="property-mobile-row"><span class="property-mobile-label" data-translate-key="label_installments">Installments:</span> <span><?php echo htmlspecialchars($p['installments'] ?: '—'); ?></span></div>
                        <div class="property-mobile-actions">
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="modal" data-bs-target="#propertyModal"
                                    onclick='openEditModal(<?php echo json_encode($p); ?>)'>
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <a href="../src/actions/handle_property_delete.php?id=<?php echo (int) $p['id']; ?>"
                               class="btn btn-sm btn-outline-danger property-delete-link"
                               data-confirm-key="confirm_delete_property">
                                <i class="fa-solid fa-trash"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add / Edit Property Modal -->
<div class="modal fade" id="propertyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="../src/actions/handle_property_save.php" method="POST" id="propertyForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="propertyModalTitle" data-translate-key="property_modal_add_title">Add Property</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="property_id" id="property_id" value="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" data-translate-key="field_developer">Developer</label>
                            <input type="text" name="developer" id="developer" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" data-translate-key="field_project">Project</label>
                            <input type="text" name="project" id="project" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" data-translate-key="field_unit_type">Unit Type</label>
                            <input type="text" name="unit_type" id="unit_type" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" data-translate-key="field_area">Area (m²)</label>
                            <input type="number" step="0.01" name="area" id="area" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" data-translate-key="field_bedrooms">Bedrooms</label>
                            <input type="number" name="bedrooms" id="bedrooms" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" data-translate-key="field_price">Price</label>
                            <input type="number" step="0.01" name="price" id="price" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" data-translate-key="field_down_payment">Down Payment</label>
                            <input type="number" step="0.01" name="down_payment" id="down_payment" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" data-translate-key="field_installments">Installments</label>
                            <input type="text" name="installments" id="installments" class="form-control" data-translate-key="field_installments_ph" placeholder="e.g. 60 months">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" data-translate-key="field_status">Status</label>
                            <select name="status" id="status" class="form-select">
                                <option value="available" data-translate-key="status_available">Available</option>
                                <option value="reserved" data-translate-key="status_reserved">Reserved</option>
                                <option value="sold" data-translate-key="status_sold">Sold</option>
                            </select>
                        </div>
                    </div>
                    <hr class="my-2">
                    <h6 class="fw-bold mb-3" data-translate-key="commission_rules_title"><i class="fa-solid fa-sack-dollar me-1"></i> Commission Rules</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" data-translate-key="field_company_commission">Company Commission</label>
                            <div class="input-group">
                                <select name="company_commission_type" id="company_commission_type" class="form-select flex-grow-0" style="max-width: 110px;">
                                    <option value="percent" data-translate-key="commission_type_percent">%</option>
                                    <option value="fixed" data-translate-key="commission_type_fixed">Fixed</option>
                                </select>
                                <input type="number" step="0.01" min="0" name="company_commission_value" id="company_commission_value" class="form-control" placeholder="0.00">
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" data-translate-key="field_sales_commission">Sales Commission</label>
                            <div class="input-group">
                                <select name="sales_commission_type" id="sales_commission_type" class="form-select flex-grow-0" style="max-width: 110px;">
                                    <option value="percent" data-translate-key="commission_type_percent">%</option>
                                    <option value="fixed" data-translate-key="commission_type_fixed">Fixed</option>
                                </select>
                                <input type="number" step="0.01" min="0" name="sales_commission_value" id="sales_commission_value" class="form-control" placeholder="0.00">
                            </div>
                        </div>
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

<script>
    function openCreateModal() {
        document.getElementById('propertyModalTitle').setAttribute('data-translate-key', 'property_modal_add_title');
        document.getElementById('propertyModalTitle').textContent = (window.tr ? window.tr('property_modal_add_title', 'Add Property') : 'Add Property');
        document.getElementById('propertyForm').reset();
        document.getElementById('property_id').value = '';
    }

    function openEditModal(p) {
        document.getElementById('propertyModalTitle').setAttribute('data-translate-key', 'property_modal_edit_title');
        document.getElementById('propertyModalTitle').textContent = (window.tr ? window.tr('property_modal_edit_title', 'Edit Property') : 'Edit Property');
        document.getElementById('property_id').value = p.id;
        document.getElementById('developer').value = p.developer || '';
        document.getElementById('project').value = p.project || '';
        document.getElementById('unit_type').value = p.unit_type || '';
        document.getElementById('area').value = p.area || '';
        document.getElementById('bedrooms').value = p.bedrooms || '';
        document.getElementById('price').value = p.price || '';
        document.getElementById('down_payment').value = p.down_payment || '';
        document.getElementById('installments').value = p.installments || '';
        document.getElementById('status').value = p.status || 'available';
        document.getElementById('company_commission_type').value = p.company_commission_type || 'percent';
        document.getElementById('company_commission_value').value = p.company_commission_value || '';
        document.getElementById('sales_commission_type').value = p.sales_commission_type || 'percent';
        document.getElementById('sales_commission_value').value = p.sales_commission_value || '';
    }

    // Re-run translations whenever the language changes, since this page's
    // table/card rows are rendered by PHP (not re-fetched) and the modal
    // title text is set dynamically above.
    document.addEventListener('languageChanged', function() {
        if (window.tr) {
            const modalTitleEl = document.getElementById('propertyModalTitle');
            const key = modalTitleEl.getAttribute('data-translate-key');
            modalTitleEl.textContent = window.tr(key, modalTitleEl.textContent);
        }
    });

    // Confirm-delete using the current language instead of a hardcoded
    // English confirm() string.
    document.querySelectorAll('.property-delete-link').forEach(function(link) {
        link.addEventListener('click', function(e) {
            const key = this.getAttribute('data-confirm-key');
            const message = (window.tr ? window.tr(key, 'Delete this property?') : 'Delete this property?');
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
</script>

<?php require_once __DIR__ . '/../src/templates/footer.php'; ?>
