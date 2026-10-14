<?php
require_once __DIR__ . '/../src/auth/auth_check.php';
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/developer_helper.php';

$company_id = $_SESSION['company_id'] ?? null;
$developer_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$developer = get_developer($conn, $developer_id, $company_id);

if (!$developer) {
    header("Location: developers.php");
    exit();
}

$projects = get_projects_by_developer($conn, $developer_id, $company_id);

require_once __DIR__ . '/../src/templates/header.php';
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/developers.css">
<div class="container-fluid py-4 px-4">
    <div class="mb-3">
        <a href="developers.php" class="text-decoration-none">
            <i class="fa-solid fa-arrow-left me-1 rtl-flip"></i> <span data-translate-key="back_to_developers">Back to Developers</span>
        </a>
    </div>

    <div class="page-title-row mb-2">
        <h2 class="fw-bold mb-0 developer-title"><i class="fa-solid fa-city developer-title-icon"></i><?php echo htmlspecialchars($developer['name']); ?></h2>
        <p class="text-muted mb-0 developer-subtitle">
            <?php if (!empty($developer['phone'])): ?><span class="developer-subtitle-item"><i class="fa-solid fa-phone"></i><?php echo htmlspecialchars($developer['phone']); ?></span><?php endif; ?>
            <?php if (!empty($developer['email'])): ?><span class="mx-2">•</span><span class="developer-subtitle-item"><i class="fa-solid fa-envelope"></i><?php echo htmlspecialchars($developer['email']); ?></span><?php endif; ?>
        </p>
    </div>
    <div class="page-action-center mb-4">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#projectModal" onclick="openCreateProjectModal()">
            <i class="fa-solid fa-plus me-1"></i> <span data-translate-key="add_project_btn">Add Project</span>
        </button>
    </div>

    <div class="card shadow-sm developer-card">
        <div class="card-body">
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th data-translate-key="col_project_name">Project Name</th>
                            <th data-translate-key="col_project_location">Location</th>
                            <th data-translate-key="col_project_properties_count">Properties</th>
                            <th data-translate-key="col_project_description">Description</th>
                            <th class="text-end" data-translate-key="col_actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($projects)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4" data-translate-key="no_projects_yet">No projects yet.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($projects as $p): ?>
                            <tr>
                                <td class="fw-semibold"><?php echo htmlspecialchars($p['name']); ?></td>
                                <td><?php echo htmlspecialchars($p['location'] ?: '—'); ?></td>
                                <td>
                                    <a href="project_properties.php?id=<?php echo (int) $p['id']; ?>" class="badge bg-primary-subtle text-primary-emphasis text-decoration-none">
                                        <?php echo (int) $p['properties_count']; ?> <span data-translate-key="col_project_properties_count">Properties</span>
                                    </a>
                                </td>
                                <td class="text-truncate" style="max-width: 260px;"><?php echo htmlspecialchars($p['description'] ?: '—'); ?></td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <a href="project_properties.php?id=<?php echo (int) $p['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                            <i class="fa-solid fa-building"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-primary"
                                                data-bs-toggle="modal" data-bs-target="#projectModal"
                                                onclick='openEditProjectModal(<?php echo json_encode($p); ?>)'>
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                        <a href="../src/actions/handle_project_delete.php?id=<?php echo (int) $p['id']; ?>&developer_id=<?php echo (int) $developer_id; ?>"
                                           class="btn btn-sm btn-outline-danger project-delete-link"
                                           data-confirm-key="confirm_delete_project">
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
                <?php if (empty($projects)): ?>
                    <p class="text-center text-muted py-4 mb-0" data-translate-key="no_projects_yet">No projects yet.</p>
                <?php endif; ?>
                <?php foreach ($projects as $p): ?>
                    <div class="developer-mobile-card">
                        <div class="developer-mobile-card-header">
                            <span class="developer-mobile-name"><?php echo htmlspecialchars($p['name']); ?></span>
                            <a href="project_properties.php?id=<?php echo (int) $p['id']; ?>" class="badge bg-primary-subtle text-primary-emphasis text-decoration-none">
                                <?php echo (int) $p['properties_count']; ?> <span data-translate-key="col_project_properties_count">Properties</span>
                            </a>
                        </div>
                        <div class="developer-mobile-row"><span class="developer-mobile-label" data-translate-key="label_project_location">Location:</span> <span><?php echo htmlspecialchars($p['location'] ?: '—'); ?></span></div>
                        <div class="developer-mobile-row"><span class="developer-mobile-label" data-translate-key="label_project_description">Description:</span> <span><?php echo htmlspecialchars($p['description'] ?: '—'); ?></span></div>
                        <div class="developer-mobile-actions">
                            <a href="project_properties.php?id=<?php echo (int) $p['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="fa-solid fa-building"></i>
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="modal" data-bs-target="#projectModal"
                                    onclick='openEditProjectModal(<?php echo json_encode($p); ?>)'>
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <a href="../src/actions/handle_project_delete.php?id=<?php echo (int) $p['id']; ?>&developer_id=<?php echo (int) $developer_id; ?>"
                               class="btn btn-sm btn-outline-danger project-delete-link"
                               data-confirm-key="confirm_delete_project">
                                <i class="fa-solid fa-trash"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add / Edit Project Modal -->
<div class="modal fade" id="projectModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="../src/actions/handle_project_save.php" method="POST" id="projectForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="projectModalTitle" data-translate-key="project_modal_add_title">Add Project</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="project_id" id="project_id" value="">
                    <input type="hidden" name="developer_id" value="<?php echo (int) $developer_id; ?>">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" data-translate-key="field_project_name">Project Name</label>
                            <input type="text" name="name" id="project_name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" data-translate-key="field_project_location">Location</label>
                            <input type="text" name="location" id="project_location" class="form-control">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label" data-translate-key="field_project_description">Description</label>
                            <textarea name="description" id="project_description" class="form-control" rows="3"></textarea>
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
    function openCreateProjectModal() {
        document.getElementById('projectModalTitle').setAttribute('data-translate-key', 'project_modal_add_title');
        document.getElementById('projectModalTitle').textContent = (window.tr ? window.tr('project_modal_add_title', 'Add Project') : 'Add Project');
        document.getElementById('projectForm').reset();
        document.getElementById('project_id').value = '';
    }

    function openEditProjectModal(p) {
        document.getElementById('projectModalTitle').setAttribute('data-translate-key', 'project_modal_edit_title');
        document.getElementById('projectModalTitle').textContent = (window.tr ? window.tr('project_modal_edit_title', 'Edit Project') : 'Edit Project');
        document.getElementById('project_id').value = p.id;
        document.getElementById('project_name').value = p.name || '';
        document.getElementById('project_location').value = p.location || '';
        document.getElementById('project_description').value = p.description || '';
    }

    document.addEventListener('languageChanged', function() {
        if (window.tr) {
            const modalTitleEl = document.getElementById('projectModalTitle');
            const key = modalTitleEl.getAttribute('data-translate-key');
            modalTitleEl.textContent = window.tr(key, modalTitleEl.textContent);
        }
    });

    document.querySelectorAll('.project-delete-link').forEach(function(link) {
        link.addEventListener('click', function(e) {
            const key = this.getAttribute('data-confirm-key');
            const message = (window.tr ? window.tr(key, 'Delete this project?') : 'Delete this project?');
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
</script>

<?php require_once __DIR__ . '/../src/templates/footer.php'; ?>
