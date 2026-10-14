<?php
/**
 * Single reminder row. Expects $r to be populated with the fields returned
 * by get_active_reminders(): id, client_name, phone_number, project_name,
 * event_title, event_date, event_time, reminder_status.
 */
$status_badge_class = 'bg-primary';
if ($r['reminder_status'] === 'overdue') $status_badge_class = 'bg-danger';
if ($r['reminder_status'] === 'today') $status_badge_class = 'bg-warning text-dark';

$status_translate_keys = [
    'overdue' => 'reminders_overdue',
    'today' => 'reminders_today',
    'upcoming' => 'reminders_upcoming',
];
$status_key = $status_translate_keys[$r['reminder_status']] ?? '';
?>
<div class="reminder-item reminder-<?php echo htmlspecialchars($r['reminder_status']); ?>">
    <div class="reminder-info">
        <h5 class="client-name"><?php echo htmlspecialchars($r['client_name']); ?></h5>
        <p class="client-phone text-muted mb-0"><?php echo htmlspecialchars($r['phone_number']); ?></p>
    </div>

    <div class="reminder-title">
        <i class="fa-solid fa-note-sticky me-2 text-muted"></i>
        <span><?php echo htmlspecialchars($r['event_title'] ?: 'Follow-up'); ?></span>
    </div>

    <div class="reminder-datetime">
        <i class="fa-regular fa-calendar me-1"></i>
        <?php echo htmlspecialchars($r['event_date']); ?>
        <?php if (!empty($r['event_time'])): ?>
            <i class="fa-regular fa-clock ms-2 me-1"></i>
            <?php echo htmlspecialchars(substr($r['event_time'], 0, 5)); ?>
        <?php endif; ?>
    </div>

    <div class="reminder-status">
        <span class="badge rounded-pill <?php echo $status_badge_class; ?> status-badge" <?php echo $status_key ? 'data-translate-key="' . $status_key . '"' : ''; ?>>
            <?php echo ucfirst(htmlspecialchars($r['reminder_status'])); ?>
        </span>
    </div>

    <div class="reminder-actions">
        <a href="client_registration.php?id=<?php echo $r['id']; ?>" class="btn-action" title="Update" data-translate-title="tooltip_update">
            <i class="fa-solid fa-pen-to-square"></i>
        </a>
        <a href="https://wa.me/<?php echo htmlspecialchars($r['phone_number']); ?>" target="_blank" class="btn-action" title="WhatsApp" data-translate-title="tooltip_whatsapp">
            <i class="fa-brands fa-whatsapp"></i>
        </a>
    </div>
</div>
