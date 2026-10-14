<?php
require_once __DIR__ . '/../src/auth/auth_check.php';
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/developer_helper.php';

$company_id = $_SESSION['company_id'] ?? null;
$developers = get_developers($conn, $company_id);

require_once __DIR__ . '/../src/templates/header.php';
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/developers.css">
<div class="container-fluid py-4 px-4">
    <div class="page-title-row mb-2">
        <h2 class="fw-bold mb-0"><i class="fa-solid fa-city me-2"></i><span data-translate-key="developers_title">Developers</span></h2>
    </div>
    <div class="page-action-center mb-4">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#developerModal" onclick="openCreateDeveloperModal()">
            <i class="fa-solid fa-plus me-1"></i> <span data-translate-key="add_developer_btn">Add Developer</span>
        </button>
    </div>

    <div class="card shadow-sm developer-card">
        <div class="card-body">
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th data-translate-key="col_dev_name">Name</th>
                            <th data-translate-key="col_dev_phone">Phone</th>
                            <th data-translate-key="col_dev_email">Email</th>
                            <th data-translate-key="col_dev_projects_count">Projects</th>
                            <th data-translate-key="col_dev_notes">Notes</th>
                            <th class="text-end" data-translate-key="col_actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($developers)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4" data-translate-key="no_developers_yet">No developers yet.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($developers as $d): ?>
                            <tr>
                                <td>
                                    <a href="developer_view.php?id=<?php echo (int) $d['id']; ?>" class="fw-semibold text-decoration-none">
                                        <?php echo htmlspecialchars($d['name']); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($d['phone'] ?: '—'); ?></td>
                                <td><?php echo htmlspecialchars($d['email'] ?: '—'); ?></td>
                                <td><span class="badge bg-primary-subtle text-primary-emphasis"><?php echo (int) $d['projects_count']; ?></span></td>
                                <td class="text-truncate" style="max-width: 220px;"><?php echo htmlspecialchars($d['notes'] ?: '—'); ?></td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <a href="developer_view.php?id=<?php echo (int) $d['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-primary"
                                                data-bs-toggle="modal" data-bs-target="#developerModal"
                                                onclick='openEditDeveloperModal(<?php echo json_encode($d); ?>)'>
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                        <a href="../src/actions/handle_developer_delete.php?id=<?php echo (int) $d['id']; ?>"
                                           class="btn btn-sm btn-outline-danger developer-delete-link"
                                           data-confirm-key="confirm_delete_developer">
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
            <div class="developer-cards d-md-none">
                <?php if (empty($developers)): ?>
                    <p class="text-center text-muted py-4 mb-0" data-translate-key="no_developers_yet">No developers yet.</p>
                <?php endif; ?>
                <?php foreach ($developers as $d): ?>
                    <div class="developer-mobile-card">
                        <div class="developer-mobile-card-header">
                            <a href="developer_view.php?id=<?php echo (int) $d['id']; ?>" class="developer-mobile-name text-decoration-none">
                                <?php echo htmlspecialchars($d['name']); ?>
                            </a>
                            <span class="badge bg-primary-subtle text-primary-emphasis"><?php echo (int) $d['projects_count']; ?> <span data-translate-key="col_dev_projects_count">Projects</span></span>
                        </div>
                        <div class="developer-mobile-row"><span class="developer-mobile-label" data-translate-key="label_dev_phone">Phone:</span> <span><?php echo htmlspecialchars($d['phone'] ?: '—'); ?></span></div>
                        <div class="developer-mobile-row"><span class="developer-mobile-label" data-translate-key="label_dev_email">Email:</span> <span><?php echo htmlspecialchars($d['email'] ?: '—'); ?></span></div>
                        <div class="developer-mobile-row"><span class="developer-mobile-label" data-translate-key="label_dev_notes">Notes:</span> <span><?php echo htmlspecialchars($d['notes'] ?: '—'); ?></span></div>
                        <div class="developer-mobile-actions">
                            <a href="developer_view.php?id=<?php echo (int) $d['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="fa-solid fa-eye"></i>
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="modal" data-bs-target="#developerModal"
                                    onclick='openEditDeveloperModal(<?php echo json_encode($d); ?>)'>
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <a href="../src/actions/handle_developer_delete.php?id=<?php echo (int) $d['id']; ?>"
                               class="btn btn-sm btn-outline-danger developer-delete-link"
                               data-confirm-key="confirm_delete_developer">
                                <i class="fa-solid fa-trash"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add / Edit Developer Modal -->
<div class="modal fade" id="developerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="../src/actions/handle_developer_save.php" method="POST" id="developerForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="developerModalTitle" data-translate-key="developer_modal_add_title">Add Developer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="developer_id" id="developer_id" value="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" data-translate-key="field_dev_name">Name</label>
                            <input type="text" name="name" id="dev_name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" data-translate-key="field_dev_phone">Phone</label>
                            <input type="text" name="phone" id="dev_phone" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" data-translate-key="field_dev_email">Email</label>
                            <input type="email" name="email" id="dev_email" class="form-control">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label" data-translate-key="field_dev_notes">Notes</label>
                            <textarea name="notes" id="dev_notes" class="form-control" rows="3"></textarea>
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
    function openCreateDeveloperModal() {
        document.getElementById('developerModalTitle').setAttribute('data-translate-key', 'developer_modal_add_title');
        document.getElementById('developerModalTitle').textContent = (window.tr ? window.tr('developer_modal_add_title', 'Add Developer') : 'Add Developer');
        document.getElementById('developerForm').reset();
        document.getElementById('developer_id').value = '';
    }

    function openEditDeveloperModal(d) {
        document.getElementById('developerModalTitle').setAttribute('data-translate-key', 'developer_modal_edit_title');
        document.getElementById('developerModalTitle').textContent = (window.tr ? window.tr('developer_modal_edit_title', 'Edit Developer') : 'Edit Developer');
        document.getElementById('developer_id').value = d.id;
        document.getElementById('dev_name').value = d.name || '';
        document.getElementById('dev_phone').value = d.phone || '';
        document.getElementById('dev_email').value = d.email || '';
        document.getElementById('dev_notes').value = d.notes || '';
    }

    document.addEventListener('languageChanged', function() {
        if (window.tr) {
            const modalTitleEl = document.getElementById('developerModalTitle');
            const key = modalTitleEl.getAttribute('data-translate-key');
            modalTitleEl.textContent = window.tr(key, modalTitleEl.textContent);
        }
    });

    document.querySelectorAll('.developer-delete-link').forEach(function(link) {
        link.addEventListener('click', function(e) {
            const key = this.getAttribute('data-confirm-key');
            const message = (window.tr ? window.tr(key, 'Delete this developer?') : 'Delete this developer?');
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
</script>

<?php require_once __DIR__ . '/../src/templates/footer.php'; ?>
