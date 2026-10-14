<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/super_admin_helper.php';
require_once __DIR__ . '/../src/backup_helper.php';
require_super_admin();

// Opportunistically run the automatic backup if one is due. This makes
// the "automatic" backup self-driving without needing a real cron job
// configured on the host: every time a super admin visits this page
// (or any page that includes this check), we backfill a missed daily
// backup if enough time has passed. A real cron entry calling
// cron_backup.php is still the more reliable way to run this — see
// that file's header comment — but this keeps things working even if
// cron isn't set up.
run_auto_backup_if_due($conn);

$backups = list_backups($conn);
$backup_status = $_GET['backup'] ?? null;
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Backups - Elite Real Estate CRM</title>

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
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold mb-0"><i class="fa-solid fa-database me-2"></i>Database Backups</h2>
            <p class="text-muted mb-0">Signed in as <?php echo htmlspecialchars($_SESSION['super_admin_username']); ?></p>
        </div>
        <div>
            <form action="../src/actions/handle_backup_run.php" method="POST" class="d-inline">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="fa-solid fa-play me-1"></i> Run Backup Now
                </button>
            </form>
            <a href="super_admin_dashboard.php" class="btn btn-outline-secondary me-2">
                <i class="fa-solid fa-arrow-left me-1"></i> Companies
            </a>
            <a href="../src/actions/handle_super_admin_logout.php" class="btn btn-outline-secondary">
                <i class="fa-solid fa-right-from-bracket me-1"></i> Logout
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

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3 backup-info-row">
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
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Size</th>
                            <th>Triggered By</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($backups)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No backups yet. Click "Run Backup Now" to create the first one.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($backups as $b): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($b['created_at']); ?></td>
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
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
