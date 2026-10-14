<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/super_admin_helper.php';
require_once __DIR__ . '/../src/storage_helper.php';
require_once __DIR__ . '/../src/backup_helper.php';
require_once __DIR__ . '/../src/plan_helper.php';
require_super_admin();

// Opportunistically run the automatic backup if one is due (see
// backups.php for the full explanation) — keeps the "automatic" daily
// backup self-driving even without a real cron job configured.
run_auto_backup_if_due($conn);

$companies = list_all_companies($conn);
$plans = list_all_plans($conn);
$active_plans = list_active_plans($conn);

// Most recent backups for the dashboard's Backup Management section.
// The full, unabridged history (with pagination-friendly limit) lives
// on the dedicated Backups page, linked from this section.
$recent_backups = list_backups($conn, 5);
$backup_status = $_GET['backup'] ?? null;
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard - Elite Real Estate CRM</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/backups.css">
</head>
<body>

<div class="container-fluid py-4 px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0"><i class="fa-solid fa-user-shield me-2"></i>Super Admin</h2>
            <p class="text-muted mb-0">Signed in as <?php echo htmlspecialchars($_SESSION['super_admin_username']); ?></p>
        </div>
        <div>
            <a href="backups.php" class="btn btn-outline-primary me-2">
                <i class="fa-solid fa-database me-1"></i> Backups
            </a>
            <a href="#plansManagementSection" class="btn btn-outline-primary me-2">
                <i class="fa-solid fa-layer-group me-1"></i> Plans
            </a>
            <button type="button" class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#createCompanyModal">
                <i class="fa-solid fa-plus me-1"></i> Add Company
            </button>
            <a href="../src/actions/handle_super_admin_logout.php" class="btn btn-outline-secondary">
                <i class="fa-solid fa-right-from-bracket me-1"></i> Logout
            </a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Company</th>
                            <th>Status</th>
                            <th>Plan</th>
                            <th>Subscription Start</th>
                            <th>Subscription End</th>
                            <th>Users</th>
                            <th>User Limit</th>
                            <th>Storage Used</th>
                            <th>Storage Limit</th>
                            <th>Clients</th>
                            <th>Created</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($companies)): ?>
                            <tr>
                                <td colspan="13" class="text-center text-muted py-4">No companies yet.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($companies as $c): ?>
                            <?php
                                $status = strtolower($c['status']);
                                $badge_class = 'bg-success';
                                if ($status === 'suspended') {
                                    $badge_class = 'bg-secondary';
                                } elseif ($status === 'expired') {
                                    $badge_class = 'bg-danger';
                                }
                                $is_past_due = !empty($c['subscription_end']) && strtotime($c['subscription_end']) < strtotime(date('Y-m-d'));
                            ?>
                            <tr>
                                <td><?php echo (int) $c['id']; ?></td>
                                <td class="fw-semibold"><?php echo htmlspecialchars($c['name']); ?></td>
                                <td>
                                    <span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars(ucfirst($c['status'])); ?></span>
                                    <?php if ($is_past_due && $status === 'active'): ?>
                                        <span class="badge bg-warning text-dark">Past due</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($c['plan_name'])): ?>
                                        <span class="badge bg-primary"><?php echo htmlspecialchars($c['plan_name']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">No Plan</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($c['subscription_start'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($c['subscription_end'] ?? '—'); ?></td>
                                <td><?php echo (int) $c['user_count']; ?></td>
                                <td><?php echo $c['max_users'] !== null ? (int) $c['max_users'] : '<span class="text-muted">Unlimited</span>'; ?></td>
                                <td><?php echo format_storage_bytes((float) $c['storage_used_bytes']); ?></td>
                                <td><?php echo number_format($c['storage_limit_gb'] !== null ? (float) $c['storage_limit_gb'] : DEFAULT_COMPANY_STORAGE_LIMIT_GB, 2); ?> GB</td>
                                <td><?php echo (int) $c['client_count']; ?></td>
                                <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($c['created_at']))); ?></td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-outline-primary"
                                                data-bs-toggle="modal" data-bs-target="#editCompanyModal"
                                                data-id="<?php echo (int) $c['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($c['name']); ?>"
                                                data-start="<?php echo htmlspecialchars($c['subscription_start'] ?? ''); ?>"
                                                data-end="<?php echo htmlspecialchars($c['subscription_end'] ?? ''); ?>"
                                                data-status="<?php echo htmlspecialchars($c['status']); ?>">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-warning"
                                                data-bs-toggle="modal" data-bs-target="#extendModal"
                                                data-id="<?php echo (int) $c['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($c['name']); ?>">
                                            <i class="fa-solid fa-calendar-plus"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-info"
                                                data-bs-toggle="modal" data-bs-target="#userLimitModal"
                                                data-id="<?php echo (int) $c['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($c['name']); ?>"
                                                data-max-users="<?php echo $c['max_users'] !== null ? (int) $c['max_users'] : ''; ?>"
                                                title="Set User Limit">
                                            <i class="fa-solid fa-users"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-info"
                                                data-bs-toggle="modal" data-bs-target="#storageLimitModal"
                                                data-id="<?php echo (int) $c['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($c['name']); ?>"
                                                data-storage-limit="<?php echo $c['storage_limit_gb'] !== null ? (float) $c['storage_limit_gb'] : ''; ?>"
                                                title="Set Storage Limit">
                                            <i class="fa-solid fa-database"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-primary"
                                                data-bs-toggle="modal" data-bs-target="#assignPlanModal"
                                                data-id="<?php echo (int) $c['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($c['name']); ?>"
                                                data-plan-id="<?php echo $c['plan_id'] !== null ? (int) $c['plan_id'] : ''; ?>"
                                                title="Assign Plan">
                                            <i class="fa-solid fa-layer-group"></i>
                                        </button>
                                        <?php if ($status === 'suspended'): ?>
                                            <form action="../src/actions/handle_super_admin_companies.php" method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="reactivate">
                                                <input type="hidden" name="company_id" value="<?php echo (int) $c['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-success" title="Reactivate">
                                                    <i class="fa-solid fa-play"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form action="../src/actions/handle_super_admin_companies.php" method="POST" class="d-inline"
                                                  onsubmit="return confirm('Suspend this company? Its users will be blocked from logging in, but data stays intact.');">
                                                <input type="hidden" name="action" value="suspend">
                                                <input type="hidden" name="company_id" value="<?php echo (int) $c['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="Suspend">
                                                    <i class="fa-solid fa-ban"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form action="../src/actions/handle_super_admin_companies.php" method="POST" class="d-inline"
                                              onsubmit="return confirm('Permanently delete this company and ALL of its data (users, clients, documents, reminders)? This cannot be undone.');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="company_id" value="<?php echo (int) $c['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Backup Management Section -->
    <div class="card shadow-sm mt-4" id="backupManagementSection">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <h5 class="fw-bold mb-0"><i class="fa-solid fa-database me-2"></i>Backup Management</h5>
                <div>
                    <form action="../src/actions/handle_backup_run.php" method="POST" class="d-inline">
                        <input type="hidden" name="redirect" value="dashboard">
                        <button type="submit" class="btn btn-primary btn-sm me-2">
                            <i class="fa-solid fa-play me-1"></i> Create Full System Backup
                        </button>
                    </form>
                    <a href="backups.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fa-solid fa-clock-rotate-left me-1"></i> View Full History
                    </a>
                </div>
            </div>

            <?php if ($backup_status === 'success'): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    Backup completed successfully.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php elseif ($backup_status === 'error'): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    Backup failed. Check the log entry below for details.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php elseif ($backup_status === 'deleted'): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    Backup deleted.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="row g-3 backup-info-row mb-3">
                <div class="col-md-4">
                    <div class="backup-info-box">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                        <div>
                            <div class="backup-info-label">Automatic backups</div>
                            <div class="backup-info-value">Every <?php echo BACKUP_MIN_INTERVAL_HOURS; ?>+ hours</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="backup-info-box">
                        <i class="fa-solid fa-box-archive"></i>
                        <div>
                            <div class="backup-info-label">Retention</div>
                            <div class="backup-info-value">Last <?php echo BACKUP_RETENTION_COUNT; ?> backups kept</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="backup-info-box">
                        <i class="fa-solid fa-lock"></i>
                        <div>
                            <div class="backup-info-label">Storage</div>
                            <div class="backup-info-value">Private, access-controlled</div>
                        </div>
                    </div>
                </div>
            </div>

            <p class="text-muted mb-2" style="font-size:0.875rem;">
                A full system backup contains every company's data. Showing the <?php echo count($recent_backups); ?> most recent —
                <a href="backups.php">view the full backup history</a>.
            </p>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Size</th>
                            <th>Triggered By</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_backups)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No backups yet. Click "Create Full System Backup" to create the first one.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($recent_backups as $b): ?>
                            <?php $created = strtotime($b['created_at']); ?>
                            <tr>
                                <td><?php echo htmlspecialchars(date('Y-m-d', $created)); ?></td>
                                <td><?php echo htmlspecialchars(date('H:i:s', $created)); ?></td>
                                <td>
                                    <span class="badge <?php echo $b['type'] === 'auto' ? 'bg-info text-dark' : 'bg-primary'; ?>">
                                        <?php echo htmlspecialchars(ucfirst($b['type'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($b['status'] === 'success'): ?>
                                        <span class="badge bg-success">Success</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger" title="<?php echo htmlspecialchars($b['error_message'] ?? ''); ?>">Failed</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $b['status'] === 'success' ? htmlspecialchars(format_backup_size($b['size_bytes'])) : '—'; ?></td>
                                <td><?php echo htmlspecialchars($b['triggered_by'] ?? '—'); ?></td>
                                <td class="text-end">
                                    <?php if ($b['status'] === 'success'): ?>
                                        <a href="../src/actions/handle_backup_download.php?id=<?php echo (int) $b['id']; ?>" class="btn btn-sm btn-outline-primary" title="Download">
                                            <i class="fa-solid fa-download"></i>
                                        </a>
                                        <form action="../src/actions/handle_backup_delete.php" method="POST" class="d-inline"
                                              onsubmit="return confirm('Permanently delete this backup file? This cannot be undone.');">
                                            <input type="hidden" name="id" value="<?php echo (int) $b['id']; ?>">
                                            <input type="hidden" name="redirect" value="dashboard">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Subscription Plans Management Section -->
    <div class="card shadow-sm mt-4" id="plansManagementSection">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <h5 class="fw-bold mb-0"><i class="fa-solid fa-layer-group me-2"></i>Subscription Plans</h5>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createPlanModal">
                    <i class="fa-solid fa-plus me-1"></i> Add Plan
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Plan Name</th>
                            <th>Monthly Price</th>
                            <th>Yearly Price</th>
                            <th>Max Users</th>
                            <th>Storage Limit</th>
                            <th>Status</th>
                            <th>Companies</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($plans)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">No plans yet. Click "Add Plan" to create the first one.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($plans as $p): ?>
                            <tr>
                                <td class="fw-semibold"><?php echo htmlspecialchars($p['name']); ?></td>
                                <td>$<?php echo number_format((float) $p['monthly_price'], 2); ?></td>
                                <td>$<?php echo number_format((float) $p['yearly_price'], 2); ?></td>
                                <td><?php echo $p['max_users'] !== null ? (int) $p['max_users'] : '<span class="text-muted">Unlimited</span>'; ?></td>
                                <td><?php echo $p['storage_limit_gb'] !== null ? number_format((float) $p['storage_limit_gb'], 2) . ' GB' : '<span class="text-muted">Default (' . DEFAULT_COMPANY_STORAGE_LIMIT_GB . ' GB)</span>'; ?></td>
                                <td>
                                    <span class="badge <?php echo $p['status'] === 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                        <?php echo htmlspecialchars(ucfirst($p['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo (int) $p['company_count']; ?></td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-outline-primary"
                                                data-bs-toggle="modal" data-bs-target="#editPlanModal"
                                                data-id="<?php echo (int) $p['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($p['name']); ?>"
                                                data-monthly="<?php echo htmlspecialchars($p['monthly_price']); ?>"
                                                data-yearly="<?php echo htmlspecialchars($p['yearly_price']); ?>"
                                                data-max-users="<?php echo $p['max_users'] !== null ? (int) $p['max_users'] : ''; ?>"
                                                data-storage-limit="<?php echo $p['storage_limit_gb'] !== null ? (float) $p['storage_limit_gb'] : ''; ?>"
                                                data-status="<?php echo htmlspecialchars($p['status']); ?>"
                                                title="Edit">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                        <form action="../src/actions/handle_super_admin_plans.php" method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="plan_id" value="<?php echo (int) $p['id']; ?>">
                                            <input type="hidden" name="status" value="<?php echo $p['status'] === 'active' ? 'inactive' : 'active'; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="<?php echo $p['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>">
                                                <i class="fa-solid <?php echo $p['status'] === 'active' ? 'fa-ban' : 'fa-play'; ?>"></i>
                                            </button>
                                        </form>
                                        <form action="../src/actions/handle_super_admin_plans.php" method="POST" class="d-inline"
                                              onsubmit="return confirm('Delete this plan? Companies currently on it will keep their existing limits but show as unassigned.');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="plan_id" value="<?php echo (int) $p['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Create Company Modal -->
<div class="modal fade" id="createCompanyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="../src/actions/handle_super_admin_companies.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add Company</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="form-label">Company Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Subscription Start</label>
                            <input type="date" name="subscription_start" class="form-control">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Subscription End</label>
                            <input type="date" name="subscription_end" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active">Active</option>
                            <option value="suspended">Suspended</option>
                            <option value="expired">Expired</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Company Modal -->
<div class="modal fade" id="editCompanyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="../src/actions/handle_super_admin_companies.php" method="POST" id="editCompanyForm">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Company</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="company_id" id="edit_company_id">
                    <div class="mb-3">
                        <label class="form-label">Company Name</label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Subscription Start</label>
                            <input type="date" name="subscription_start" id="edit_start" class="form-control">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Subscription End</label>
                            <input type="date" name="subscription_end" id="edit_end" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" id="edit_status" class="form-select">
                            <option value="active">Active</option>
                            <option value="suspended">Suspended</option>
                            <option value="expired">Expired</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Extend Subscription Modal -->
<div class="modal fade" id="extendModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="../src/actions/handle_super_admin_companies.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Extend Subscription</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="extend">
                    <input type="hidden" name="company_id" id="extend_company_id">
                    <p>Extend subscription for <strong id="extend_company_name"></strong> by:</p>
                    <select name="days" class="form-select">
                        <option value="30">30 days</option>
                        <option value="90">90 days</option>
                        <option value="180">180 days</option>
                        <option value="365">365 days</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Extend</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Set User Limit Modal -->
<div class="modal fade" id="userLimitModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="../src/actions/handle_super_admin_companies.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Set User Limit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="set_user_limit">
                    <input type="hidden" name="company_id" id="userlimit_company_id">
                    <p>Maximum number of users for <strong id="userlimit_company_name"></strong>:</p>
                    <input type="number" min="0" name="max_users" id="userlimit_max_users" class="form-control" placeholder="Leave blank for unlimited">
                    <small class="text-muted">When this limit is reached, no new users can be added to this company.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Set Storage Limit Modal -->
<div class="modal fade" id="storageLimitModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="../src/actions/handle_super_admin_companies.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Set Storage Limit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="set_storage_limit">
                    <input type="hidden" name="company_id" id="storagelimit_company_id">
                    <p>Storage quota (in GB) for <strong id="storagelimit_company_name"></strong>:</p>
                    <div class="input-group">
                        <input type="number" min="0" step="0.5" name="storage_limit_gb" id="storagelimit_storage_limit_gb" class="form-control" placeholder="Leave blank for default (<?php echo DEFAULT_COMPANY_STORAGE_LIMIT_GB; ?> GB)">
                        <span class="input-group-text">GB</span>
                    </div>
                    <small class="text-muted">When this limit is reached, the company can no longer upload new files.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Create Plan Modal -->
<div class="modal fade" id="createPlanModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="../src/actions/handle_super_admin_plans.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add Plan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="form-label">Plan Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Monthly Price</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" min="0" step="0.01" name="monthly_price" class="form-control" value="0" required>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Yearly Price</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" min="0" step="0.01" name="yearly_price" class="form-control" value="0" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Maximum Users</label>
                            <input type="number" min="0" name="max_users" class="form-control" placeholder="Leave blank for unlimited">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Storage Limit</label>
                            <div class="input-group">
                                <input type="number" min="0" step="0.5" name="storage_limit_gb" class="form-control" placeholder="Default (<?php echo DEFAULT_COMPANY_STORAGE_LIMIT_GB; ?> GB)">
                                <span class="input-group-text">GB</span>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Plan Modal -->
<div class="modal fade" id="editPlanModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="../src/actions/handle_super_admin_plans.php" method="POST" id="editPlanForm">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Plan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="plan_id" id="editplan_id">
                    <div class="mb-3">
                        <label class="form-label">Plan Name</label>
                        <input type="text" name="name" id="editplan_name" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Monthly Price</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" min="0" step="0.01" name="monthly_price" id="editplan_monthly" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Yearly Price</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" min="0" step="0.01" name="yearly_price" id="editplan_yearly" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Maximum Users</label>
                            <input type="number" min="0" name="max_users" id="editplan_max_users" class="form-control" placeholder="Leave blank for unlimited">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Storage Limit</label>
                            <div class="input-group">
                                <input type="number" min="0" step="0.5" name="storage_limit_gb" id="editplan_storage_limit" class="form-control" placeholder="Default (<?php echo DEFAULT_COMPANY_STORAGE_LIMIT_GB; ?> GB)">
                                <span class="input-group-text">GB</span>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" id="editplan_status" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <small class="text-muted">Changing the limits here automatically updates every company currently on this plan.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assign Plan Modal -->
<div class="modal fade" id="assignPlanModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="../src/actions/handle_super_admin_plans.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Assign Plan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="assign">
                    <input type="hidden" name="company_id" id="assignplan_company_id">
                    <p>Plan for <strong id="assignplan_company_name"></strong>:</p>
                    <select name="plan_id" id="assignplan_plan_id" class="form-select">
                        <option value="">No Plan (manual limits)</option>
                        <?php foreach ($active_plans as $ap): ?>
                            <option value="<?php echo (int) $ap['id']; ?>">
                                <?php echo htmlspecialchars($ap['name']); ?>
                                (<?php echo $ap['max_users'] !== null ? (int) $ap['max_users'] . ' users' : 'unlimited users'; ?>,
                                <?php echo $ap['storage_limit_gb'] !== null ? number_format((float) $ap['storage_limit_gb'], 2) . ' GB' : 'default storage'; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Assigning a plan automatically applies its user and storage limits to this company, replacing any manually-set limits.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Assign</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('editCompanyModal').addEventListener('show.bs.modal', function (event) {
        const btn = event.relatedTarget;
        document.getElementById('edit_company_id').value = btn.getAttribute('data-id');
        document.getElementById('edit_name').value = btn.getAttribute('data-name');
        document.getElementById('edit_start').value = btn.getAttribute('data-start');
        document.getElementById('edit_end').value = btn.getAttribute('data-end');
        document.getElementById('edit_status').value = btn.getAttribute('data-status');
    });

    document.getElementById('extendModal').addEventListener('show.bs.modal', function (event) {
        const btn = event.relatedTarget;
        document.getElementById('extend_company_id').value = btn.getAttribute('data-id');
        document.getElementById('extend_company_name').textContent = btn.getAttribute('data-name');
    });

    document.getElementById('userLimitModal').addEventListener('show.bs.modal', function (event) {
        const btn = event.relatedTarget;
        document.getElementById('userlimit_company_id').value = btn.getAttribute('data-id');
        document.getElementById('userlimit_company_name').textContent = btn.getAttribute('data-name');
        document.getElementById('userlimit_max_users').value = btn.getAttribute('data-max-users') || '';
    });

    document.getElementById('storageLimitModal').addEventListener('show.bs.modal', function (event) {
        const btn = event.relatedTarget;
        document.getElementById('storagelimit_company_id').value = btn.getAttribute('data-id');
        document.getElementById('storagelimit_company_name').textContent = btn.getAttribute('data-name');
        document.getElementById('storagelimit_storage_limit_gb').value = btn.getAttribute('data-storage-limit') || '';
    });

    document.getElementById('editPlanModal').addEventListener('show.bs.modal', function (event) {
        const btn = event.relatedTarget;
        document.getElementById('editplan_id').value = btn.getAttribute('data-id');
        document.getElementById('editplan_name').value = btn.getAttribute('data-name');
        document.getElementById('editplan_monthly').value = btn.getAttribute('data-monthly');
        document.getElementById('editplan_yearly').value = btn.getAttribute('data-yearly');
        document.getElementById('editplan_max_users').value = btn.getAttribute('data-max-users') || '';
        document.getElementById('editplan_storage_limit').value = btn.getAttribute('data-storage-limit') || '';
        document.getElementById('editplan_status').value = btn.getAttribute('data-status');
    });

    document.getElementById('assignPlanModal').addEventListener('show.bs.modal', function (event) {
        const btn = event.relatedTarget;
        document.getElementById('assignplan_company_id').value = btn.getAttribute('data-id');
        document.getElementById('assignplan_company_name').textContent = btn.getAttribute('data-name');
        document.getElementById('assignplan_plan_id').value = btn.getAttribute('data-plan-id') || '';
    });
</script>

</body>
</html>
