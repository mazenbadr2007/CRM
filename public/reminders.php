<?php
require_once __DIR__ . '/../src/auth/auth_check.php';
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/reminder_helper.php';
require_once __DIR__ . '/../src/access_helper.php';

$user_id = $_SESSION['user_id'];
$is_manager = access_can_see_team();

$reminders = get_active_reminders($conn, $user_id, $is_manager);
$counts = get_reminder_counts($conn, $user_id, $is_manager);
$history = get_reminder_history($conn, $user_id, $is_manager, null, 100);

// Split into buckets for display.
$overdue_list = array_values(array_filter($reminders, fn($r) => $r['reminder_status'] === 'overdue'));
$today_list   = array_values(array_filter($reminders, fn($r) => $r['reminder_status'] === 'today'));
$upcoming_list = array_values(array_filter($reminders, fn($r) => $r['reminder_status'] === 'upcoming'));

require_once __DIR__ . '/../src/templates/header.php';
?>
<link rel="stylesheet" href="css/reminders.css">

<div class="container-fluid">
    <div class="page-header">
        <h1 class="page-title" data-translate-key="reminders_title">Reminders</h1>
        <p class="page-subtitle" data-translate-key="reminders_subtitle">Automatic reminders based on each client's follow-up date &amp; time.</p>
    </div>

    <div class="reminder-summary-cards">
        <div class="summary-card overdue-card">
            <div class="summary-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <div class="summary-info">
                <span class="summary-count"><?php echo count($overdue_list); ?></span>
                <span class="summary-label" data-translate-key="reminders_overdue">Overdue</span>
            </div>
        </div>
        <div class="summary-card today-card">
            <div class="summary-icon"><i class="fa-solid fa-bell"></i></div>
            <div class="summary-info">
                <span class="summary-count"><?php echo count($today_list); ?></span>
                <span class="summary-label" data-translate-key="reminders_today">Due Today</span>
            </div>
        </div>
        <div class="summary-card upcoming-card">
            <div class="summary-icon"><i class="fa-solid fa-calendar-days"></i></div>
            <div class="summary-info">
                <span class="summary-count"><?php echo count($upcoming_list); ?></span>
                <span class="summary-label" data-translate-key="reminders_upcoming">Upcoming</span>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs reminder-tabs" id="reminderTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="active-tab" data-bs-toggle="tab" data-bs-target="#active-pane" type="button" role="tab" data-translate-key="reminders_tab_active">Active Reminders</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history-pane" type="button" role="tab" data-translate-key="reminders_tab_history">History</button>
        </li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="active-pane" role="tabpanel">

            <?php if (empty($reminders)): ?>
                <div class="card text-center p-5 mt-3">
                    <p class="text-muted fs-4" data-translate-key="no_reminders_found">No reminders scheduled.</p>
                </div>
            <?php else: ?>

                <?php if (!empty($overdue_list)): ?>
                    <h6 class="reminder-group-title text-danger mt-4" data-translate-key="reminders_overdue">Overdue</h6>
                    <div class="reminder-list-container">
                        <?php foreach ($overdue_list as $r): ?>
                            <?php include __DIR__ . '/../src/templates/_reminder_item.php'; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($today_list)): ?>
                    <h6 class="reminder-group-title text-warning mt-4" data-translate-key="reminders_today">Due Today</h6>
                    <div class="reminder-list-container">
                        <?php foreach ($today_list as $r): ?>
                            <?php include __DIR__ . '/../src/templates/_reminder_item.php'; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($upcoming_list)): ?>
                    <h6 class="reminder-group-title text-primary mt-4" data-translate-key="reminders_upcoming">Upcoming</h6>
                    <div class="reminder-list-container">
                        <?php foreach ($upcoming_list as $r): ?>
                            <?php include __DIR__ . '/../src/templates/_reminder_item.php'; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>

        <div class="tab-pane fade" id="history-pane" role="tabpanel">
            <div class="reminder-history-container mt-3">
                <?php if (empty($history)): ?>
                    <div class="card text-center p-5">
                        <p class="text-muted fs-4" data-translate-key="no_reminder_history">No reminder history yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($history as $h): ?>
                        <?php
                            $status_icon = 'fa-circle-info';
                            $status_class = 'text-secondary';
                            $history_status_keys = [
                                'created' => 'history_status_created',
                                'rescheduled' => 'history_status_rescheduled',
                                'cleared' => 'history_status_cleared',
                                'completed' => 'history_status_completed',
                                'dismissed' => 'history_status_dismissed',
                            ];
                            $history_status_key = $history_status_keys[$h['status']] ?? '';
                            switch ($h['status']) {
                                case 'created':
                                    $status_icon = 'fa-plus';
                                    $status_class = 'text-primary';
                                    break;
                                case 'rescheduled':
                                    $status_icon = 'fa-clock-rotate-left';
                                    $status_class = 'text-warning';
                                    break;
                                case 'cleared':
                                case 'completed':
                                    $status_icon = 'fa-check';
                                    $status_class = 'text-success';
                                    break;
                                case 'dismissed':
                                    $status_icon = 'fa-xmark';
                                    $status_class = 'text-danger';
                                    break;
                            }
                        ?>
                        <div class="history-item">
                            <div class="history-icon <?php echo $status_class; ?>">
                                <i class="fa-solid <?php echo $status_icon; ?>"></i>
                            </div>
                            <div class="history-info">
                                <p class="mb-0">
                                    <strong><?php if (empty($h['client_name'])): ?><span data-translate-key="unknown_client">Unknown Client</span><?php else: echo htmlspecialchars($h['client_name']); endif; ?></strong>
                                    &mdash;
                                    <span class="text-capitalize" <?php echo $history_status_key ? 'data-translate-key="' . $history_status_key . '"' : ''; ?>><?php echo htmlspecialchars($h['status']); ?></span>
                                    <?php if (!empty($h['reminder_date'])): ?>
                                        <span class="text-muted">
                                            (<?php echo htmlspecialchars($h['reminder_date']); ?><?php echo !empty($h['reminder_time']) ? ' ' . htmlspecialchars($h['reminder_time']) : ''; ?>)
                                        </span>
                                    <?php endif; ?>
                                </p>
                                <?php if (!empty($h['note'])): ?>
                                    <p class="text-muted small mb-0"><?php echo htmlspecialchars($h['note']); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="history-timestamp text-muted small">
                                <?php echo htmlspecialchars($h['created_at']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../src/templates/footer.php';
?>
