<?php
require_once __DIR__ . '/../src/auth/auth_check.php';
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/document_helper.php';
require_once __DIR__ . '/../src/storage_helper.php';
require_once __DIR__ . '/../src/access_helper.php';

$user_id = $_SESSION['user_id'];
// Role-based scope (access_helper.php): admin = company, manager = own team, employee = own assigned clients.
// Admin and manager also get the owning employee's name on the client record.
$show_employee_name = access_can_see_team();
$company_id = $_SESSION['company_id'] ?? null;

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: clients_dashboard.php');
    exit();
}
$client_id = (int) $_GET['id'];

$client = fetch_accessible_client($conn, $client_id, $show_employee_name);
if (!$client) {
    header('Location: clients_dashboard.php');
    exit();
}

// Fetch the activity log entries, newest first.
$log_stmt = $conn->prepare("SELECT * FROM client_activity_log WHERE client_id = ? AND company_id = ? ORDER BY created_at DESC, id DESC");
$log_stmt->bind_param("ii", $client_id, $company_id);
$log_stmt->execute();
$log_result = $log_stmt->get_result();
$activities = $log_result->fetch_all(MYSQLI_ASSOC);
$log_stmt->close();

// Fetch this client's documents, grouped by category (Images, PDF Files,
// Contracts, Voice Notes).
$documents_by_category = get_client_documents($conn, $client_id);

// Company-wide storage quota snapshot (used / available / limit), shown
// above the upload widgets and used to block uploads once the quota is
// reached.
$storage_summary = get_company_storage_summary($conn, $company_id);

// Map action types to an icon + color theme for the UI.
$action_meta = [
    'client_created'     => ['icon' => 'fa-solid fa-user-plus',        'color' => '#0d6efd', 'label' => 'Client Created'],
    'client_updated'     => ['icon' => 'fa-solid fa-pen',               'color' => '#6c757d', 'label' => 'Client Updated'],
    'phone_call'         => ['icon' => 'fa-solid fa-phone',             'color' => '#20c997', 'label' => 'Phone Call'],
    'visit_scheduled'    => ['icon' => 'fa-solid fa-calendar-plus',     'color' => '#B48C42', 'label' => 'Visit Scheduled'],
    'visit_completed'    => ['icon' => 'fa-solid fa-calendar-check',    'color' => '#198754', 'label' => 'Visit Completed'],
    'status_changed'     => ['icon' => 'fa-solid fa-arrows-rotate',     'color' => '#fd7e14', 'label' => 'Status Changed'],
    'pipeline_stage_changed' => ['icon' => 'fa-solid fa-columns',       'color' => '#B48C42', 'label' => 'Pipeline Stage Changed'],
    'notes_added'        => ['icon' => 'fa-solid fa-note-sticky',       'color' => '#6f42c1', 'label' => 'Notes Added'],
    'followup_created'   => ['icon' => 'fa-solid fa-bell',              'color' => '#0dcaf0', 'label' => 'Follow-up Created'],
    'followup_completed' => ['icon' => 'fa-solid fa-circle-check',      'color' => '#198754', 'label' => 'Follow-up Completed'],
    'document_uploaded'  => ['icon' => 'fa-solid fa-file-arrow-up',      'color' => '#0d6efd', 'label' => 'Document Uploaded'],
    'document_deleted'   => ['icon' => 'fa-solid fa-file-circle-minus',  'color' => '#dc3545', 'label' => 'Document Deleted'],
];

require_once __DIR__ . '/../src/templates/header.php';
?>
<link rel="stylesheet" href="css/client_timeline.css">
<link rel="stylesheet" href="css/client_documents.css">

<div class="container-fluid">
    <div class="page-header d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <h1 class="page-title" data-translate-key="activity_timeline_title">Activity Timeline</h1>
            <p class="page-subtitle">
                <span data-translate-key="activity_timeline_subtitle">Complete history of every action for</span>
                <strong><?php echo htmlspecialchars($client['client_name']); ?></strong>
            </p>
        </div>
        <a href="clients_dashboard.php" class="btn btn-outline-secondary btn-back">
            <i class="fa-solid fa-arrow-left me-2"></i><span data-translate-key="back_to_dashboard">Back to Dashboard</span>
        </a>
    </div>

    <div class="card timeline-client-card mb-4">
        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h5 class="mb-1"><?php echo htmlspecialchars($client['client_name']); ?></h5>
                <small class="text-muted"><?php echo htmlspecialchars($client['phone_number']); ?></small>
            </div>
            <?php
                $status = $client['final_status'] ?? 'Follow-up';
                $badge_class = 'bg-secondary';
                if ($status == 'Interested') $badge_class = 'bg-success';
                if ($status == 'Follow-up') $badge_class = 'bg-warning text-dark';
                if ($status == 'Not Interested') $badge_class = 'bg-danger';
                if ($status == 'Contracted') $badge_class = 'bg-primary';
            ?>
            <div class="badge rounded-pill <?php echo $badge_class; ?> status-badge"><?php echo T($status); ?></div>
            <a href="client_registration.php?id=<?php echo $client['id']; ?>" class="btn btn-sm btn-update">
                <i class="fa-solid fa-pen me-1"></i> <span data-translate-key="update_btn">Update</span>
            </a>
        </div>
    </div>

    <?php
        if (isset($_GET['doc_success'])) {
            echo '<div class="alert alert-success doc-alert"><i class="fa-solid fa-circle-check me-2"></i>Document uploaded successfully.</div>';
        } elseif (isset($_GET['doc_deleted'])) {
            echo '<div class="alert alert-success doc-alert"><i class="fa-solid fa-circle-check me-2"></i>Document deleted.</div>';
        } elseif (isset($_GET['doc_error'])) {
            $doc_error_messages = [
                'invalid_request' => 'Invalid upload request.',
                'no_file'         => 'Please choose a file to upload.',
                'upload_failed'   => 'The file could not be uploaded. Please try again.',
                'too_large'       => 'That file is too large (max 20 MB).',
                'invalid_type'    => 'That file type is not allowed for this category.',
                'storage_limit'   => storage_limit_reached_message(),
            ];
            $msg = $doc_error_messages[$_GET['doc_error']] ?? 'Something went wrong with the upload.';
            echo '<div class="alert alert-danger doc-alert"><i class="fa-solid fa-triangle-exclamation me-2"></i>' . htmlspecialchars($msg) . '</div>';
        }
    ?>

    <div class="card documents-card mb-4">
        <div class="card-body">
            <h5 class="documents-title"><i class="fa-solid fa-folder-open me-2"></i><span data-translate-key="client_documents_title">Client Documents</span></h5>

            <div class="storage-usage-widget mb-3">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-1">
                    <small class="text-muted">
                        <span data-translate-key="storage_used_label">Used Storage</span>:
                        <strong><?php echo format_storage_bytes($storage_summary['used_bytes']); ?></strong>
                        / <?php echo format_storage_bytes($storage_summary['limit_bytes']); ?>
                    </small>
                    <small class="text-muted">
                        <span data-translate-key="storage_available_label">Available Storage</span>:
                        <strong><?php echo format_storage_bytes($storage_summary['available_bytes']); ?></strong>
                    </small>
                </div>
                <div class="progress" style="height: 8px;">
                    <div class="progress-bar <?php echo $storage_summary['limit_reached'] ? 'bg-danger' : ($storage_summary['percent_used'] >= 85 ? 'bg-warning' : 'bg-primary'); ?>"
                         role="progressbar"
                         style="width: <?php echo $storage_summary['percent_used']; ?>%;"
                         aria-valuenow="<?php echo $storage_summary['percent_used']; ?>" aria-valuemin="0" aria-valuemax="100">
                    </div>
                </div>
                <?php if ($storage_summary['limit_reached']): ?>
                    <small class="text-danger d-block mt-1">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i>
                        <span data-translate-key="storage_limit_reached_notice"><?php echo htmlspecialchars(storage_limit_reached_message()); ?></span>
                    </small>
                <?php endif; ?>
            </div>

            <ul class="nav nav-tabs documents-tabs" role="tablist">
                <?php foreach (DOCUMENT_CATEGORIES as $cat_key => $cat_label): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $cat_key === 'image' ? 'active' : ''; ?>"
                                id="tab-btn-<?php echo $cat_key; ?>"
                                data-bs-toggle="tab"
                                data-bs-target="#tab-<?php echo $cat_key; ?>"
                                type="button" role="tab">
                            <i class="<?php echo document_category_icon($cat_key); ?> me-1"></i>
                            <?php echo T($cat_label); ?>
                            <span class="badge rounded-pill doc-count-badge"><?php echo count($documents_by_category[$cat_key]); ?></span>
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="tab-content documents-tab-content">
                <?php foreach (DOCUMENT_CATEGORIES as $cat_key => $cat_label): ?>
                    <div class="tab-pane fade <?php echo $cat_key === 'image' ? 'show active' : ''; ?>" id="tab-<?php echo $cat_key; ?>" role="tabpanel">

                        <?php if ($storage_summary['limit_reached']): ?>
                            <div class="alert alert-warning py-2 px-3 mb-3" role="alert">
                                <i class="fa-solid fa-lock me-1"></i>
                                <span data-translate-key="storage_limit_upload_disabled">Uploads are disabled — your company's storage limit has been reached.</span>
                            </div>
                        <?php else: ?>
                            <form action="../src/actions/handle_document_upload.php" method="POST" enctype="multipart/form-data" class="doc-upload-form">
                                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                                <input type="hidden" name="category" value="<?php echo $cat_key; ?>">
                                <input type="file" name="document" id="file-input-<?php echo $cat_key; ?>" class="form-control doc-file-input" required>
                                <button type="submit" class="btn btn-sm btn-doc-upload">
                                    <i class="fa-solid fa-upload me-1"></i><span data-translate-key="upload_btn">Upload</span>
                                </button>
                            </form>
                        <?php endif; ?>

                        <?php if (empty($documents_by_category[$cat_key])): ?>
                            <p class="text-muted doc-empty" data-translate-key="no_documents_yet">No files uploaded yet.</p>
                        <?php else: ?>
                            <div class="doc-list">
                                <?php foreach ($documents_by_category[$cat_key] as $doc): ?>
                                    <div class="doc-item">
                                        <div class="doc-item-info">
                                            <i class="<?php echo document_category_icon($cat_key); ?> doc-item-icon"></i>
                                            <div class="doc-item-meta">
                                                <span class="doc-item-name"><?php echo htmlspecialchars($doc['original_name']); ?></span>
                                                <span class="doc-item-sub">
                                                    <?php echo format_document_size($doc['file_size']); ?>
                                                    &middot; <?php echo (new DateTime($doc['uploaded_at']))->format('M j, Y'); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="doc-item-actions">
                                            <a href="document_download.php?id=<?php echo $doc['id']; ?>&mode=preview" target="_blank" class="btn btn-sm btn-doc-action" title="Preview">
                                                <i class="fa-solid fa-eye"></i>
                                            </a>
                                            <a href="document_download.php?id=<?php echo $doc['id']; ?>&mode=download" class="btn btn-sm btn-doc-action" title="Download">
                                                <i class="fa-solid fa-download"></i>
                                            </a>
                                            <form action="document_delete.php" method="POST" class="doc-delete-form" onsubmit="return confirm(window.tr ? window.tr('confirm_delete_file', 'Delete this file? This cannot be undone.') : 'Delete this file? This cannot be undone.');">
                                                <input type="hidden" name="id" value="<?php echo $doc['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-doc-action btn-doc-delete" title="Delete" data-translate-title="tooltip_delete">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="timeline-wrapper">
        <?php if (empty($activities)): ?>
            <div class="card text-center p-5">
                <p class="text-muted fs-5 mb-0" data-translate-key="no_activity_yet">No activity recorded yet for this client.</p>
            </div>
        <?php else: ?>
            <div class="timeline">
                <?php foreach ($activities as $activity):
                    $meta = $action_meta[$activity['action_type']] ?? ['icon' => 'fa-solid fa-circle-info', 'color' => '#6c757d', 'label' => $activity['action_type']];
                    $date_obj = new DateTime($activity['created_at']);
                ?>
                    <div class="timeline-item">
                        <div class="timeline-icon" style="background-color: <?php echo $meta['color']; ?>;">
                            <i class="<?php echo $meta['icon']; ?>"></i>
                        </div>
                        <div class="timeline-content">
                            <div class="timeline-header">
                                <span class="timeline-action" style="color: <?php echo $meta['color']; ?>;"><?php echo T($meta['label']); ?></span>
                                <span class="timeline-datetime">
                                    <i class="fa-regular fa-calendar me-1"></i><?php echo $date_obj->format('M j, Y'); ?>
                                    <i class="fa-regular fa-clock ms-2 me-1"></i><?php echo $date_obj->format('g:i A'); ?>
                                </span>
                            </div>
                            <p class="timeline-description"><?php echo htmlspecialchars($activity['description']); ?></p>
                            <div class="timeline-employee">
                                <i class="fa-solid fa-user me-1"></i><?php echo htmlspecialchars($activity['employee_name'] ?? 'Unknown'); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/../src/templates/footer.php';
?>
