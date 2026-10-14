<?php
require_once __DIR__ . '/../src/auth/manager_check.php';
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/user_helper.php';
require_once __DIR__ . '/../src/access_helper.php';

$company_id = $_SESSION['company_id'] ?? null;
$users = get_company_users($conn, $company_id);
$sales_managers = get_company_sales_managers($conn, $company_id);

function crm_role_label($role) {
    switch (normalize_user_role($role)) {
        case ROLE_COMPANY_ADMIN:
            return ['key' => 'role_company_admin', 'text' => 'Company Admin'];
        case ROLE_SALES_MANAGER:
            return ['key' => 'role_sales_manager', 'text' => 'Sales Manager'];
        default:
            return ['key' => 'role_sales_employee', 'text' => 'Sales Employee'];
    }
}

require_once __DIR__ . '/../src/templates/header.php';
?>
<div class="container-fluid py-4 px-4">
    <div class="page-title-row mb-2">
        <h2 class="fw-bold mb-0"><i class="fa-solid fa-users me-2"></i><span data-translate-key="users_management_title">Users Management</span></h2>
    </div>
    <div class="page-action-center mb-4">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" onclick="openCreateUserModal()">
            <i class="fa-solid fa-plus me-1"></i> <span data-translate-key="add_user_btn">Add User</span>
        </button>
    </div>

    <?php if (isset($_GET['error'])): ?>
        <?php
            $error_key = 'error_generic_fields';
            $error_text = 'Please fill all fields correctly.';
            switch ($_GET['error']) {
                case 'username_taken':
                    $error_key = 'error_username_taken';
                    $error_text = 'Username is already taken.';
                    break;
                case 'email_taken':
                    $error_key = 'error_email_taken';
                    $error_text = 'This email is already in use.';
                    break;
                case 'invalid_email':
                    $error_key = 'error_invalid_email';
                    $error_text = 'Please enter a valid email address.';
                    break;
                case 'password_mismatch':
                    $error_key = 'error_password_mismatch';
                    $error_text = 'Passwords do not match.';
                    break;
                case 'user_limit_reached':
                    $error_key = 'error_user_limit_reached';
                    $error_text = 'Your plan has reached the maximum number of users. Please upgrade your subscription.';
                    break;
                case 'not_found':
                    $error_key = 'error_user_not_found';
                    $error_text = 'User not found.';
                    break;
                case 'empty_fields':
                    $error_key = 'error_generic_fields';
                    $error_text = 'Please fill all fields correctly.';
                    break;
            }
        ?>
        <div class="alert alert-danger" data-translate-key="<?php echo $error_key; ?>"><?php echo $error_text; ?></div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th data-translate-key="col_user_username">Username</th>
                            <th data-translate-key="col_user_email">Email</th>
                            <th data-translate-key="col_user_role">Role</th>
                            <th data-translate-key="col_user_manager">Sales Manager</th>
                            <th data-translate-key="col_user_created_at">Created</th>
                            <th class="text-end" data-translate-key="col_actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4" data-translate-key="no_users_yet">No users yet.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($users as $u): ?>
                            <?php
                                $role_meta = crm_role_label($u['role'] ?? '');
                                $manager_name = '—';
                                if (!empty($u['manager_id'])) {
                                    foreach ($users as $maybe_mgr) {
                                        if ((int) $maybe_mgr['id'] === (int) $u['manager_id']) {
                                            $manager_name = $maybe_mgr['username'];
                                            break;
                                        }
                                    }
                                }
                            ?>
                            <tr>
                                <td class="fw-semibold"><?php echo htmlspecialchars($u['username']); ?></td>
                                <td><?php echo htmlspecialchars($u['email'] ?: '—'); ?></td>
                                <td><span data-translate-key="<?php echo htmlspecialchars($role_meta['key']); ?>"><?php echo htmlspecialchars($role_meta['text']); ?></span></td>
                                <td><?php echo htmlspecialchars($manager_name); ?></td>
                                <td><?php echo htmlspecialchars($u['created_at']); ?></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                            data-bs-toggle="modal" data-bs-target="#userModal"
                                            onclick='openEditUserModal(<?php echo json_encode(['id' => $u['id'], 'username' => $u['username'], 'email' => $u['email'], 'role' => normalize_user_role($u['role'] ?? ''), 'manager_id' => $u['manager_id'], 'is_self' => ((int) $u['id'] === (int) ($_SESSION['user_id'] ?? 0))]); ?>)'>
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add / Edit User Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="../src/actions/handle_user_save.php" method="POST" id="userForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="userModalTitle" data-translate-key="user_modal_add_title">Add User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="user_id" value="">
                    <div class="mb-3">
                        <label class="form-label" data-translate-key="field_user_username">Username</label>
                        <input type="text" name="username" id="user_username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" data-translate-key="field_user_email">Email</label>
                        <input type="email" name="email" id="user_email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" data-translate-key="field_user_role">Role</label>
                        <select name="role" id="user_role" class="form-select" onchange="toggleUserManagerField()">
                            <option value="company_admin" data-translate-key="role_company_admin">Company Admin</option>
                            <option value="sales_manager" data-translate-key="role_sales_manager">Sales Manager</option>
                            <option value="sales_employee" data-translate-key="role_sales_employee">Sales Employee</option>
                        </select>
                    </div>
                    <div class="mb-3" id="user_manager_wrap">
                        <label class="form-label" data-translate-key="field_user_manager">Sales Manager</label>
                        <select name="manager_id" id="user_manager_id" class="form-select">
                            <option value="" data-translate-key="field_user_manager_none">—</option>
                            <?php foreach ($sales_managers as $mgr): ?>
                                <option value="<?php echo (int) $mgr['id']; ?>"><?php echo htmlspecialchars($mgr['username']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" data-translate-key="field_user_password">Password</label>
                        <input type="password" name="password" id="user_password" class="form-control">
                        <small class="text-muted" id="user_password_hint" data-translate-key="field_user_password_hint">Required for new users. Leave blank to keep the current password.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" data-translate-key="field_user_confirm_password">Confirm Password</label>
                        <input type="password" name="confirm_password" id="user_confirm_password" class="form-control">
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
    function toggleUserManagerField() {
        const role = document.getElementById('user_role').value;
        document.getElementById('user_manager_wrap').style.display = (role === 'sales_employee') ? '' : 'none';
        if (role !== 'sales_employee') {
            document.getElementById('user_manager_id').value = '';
        }
    }

    function openCreateUserModal() {
        document.getElementById('userModalTitle').setAttribute('data-translate-key', 'user_modal_add_title');
        document.getElementById('userModalTitle').textContent = (window.tr ? window.tr('user_modal_add_title', 'Add User') : 'Add User');
        document.getElementById('userForm').reset();
        document.getElementById('user_id').value = '';
        document.getElementById('user_role').value = 'sales_employee';
        document.getElementById('user_role').disabled = false;
        document.getElementById('user_password').required = true;
        document.getElementById('user_confirm_password').required = true;
        toggleUserManagerField();
    }

    function openEditUserModal(u) {
        document.getElementById('userModalTitle').setAttribute('data-translate-key', 'user_modal_edit_title');
        document.getElementById('userModalTitle').textContent = (window.tr ? window.tr('user_modal_edit_title', 'Edit User') : 'Edit User');
        document.getElementById('user_id').value = u.id;
        document.getElementById('user_username').value = u.username || '';
        document.getElementById('user_email').value = u.email || '';
        document.getElementById('user_role').value = u.role || 'sales_employee';
        // Own account: role is locked (also enforced in handle_user_save.php).
        document.getElementById('user_role').disabled = !!u.is_self;
        document.getElementById('user_manager_id').value = u.manager_id || '';
        document.getElementById('user_password').value = '';
        document.getElementById('user_confirm_password').value = '';
        document.getElementById('user_password').required = false;
        document.getElementById('user_confirm_password').required = false;
        toggleUserManagerField();
    }

    document.addEventListener('languageChanged', function() {
        if (window.tr) {
            const modalTitleEl = document.getElementById('userModalTitle');
            const key = modalTitleEl.getAttribute('data-translate-key');
            modalTitleEl.textContent = window.tr(key, modalTitleEl.textContent);
        }
    });
</script>

<?php require_once __DIR__ . '/../src/templates/footer.php'; ?>
