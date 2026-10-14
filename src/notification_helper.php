<?php
/**
 * Notification Center helper.
 *
 * Adds one new, self-healing table (`notifications`) — created the same
 * way `client_activity_log` / `client_reminders` are, via
 * CREATE TABLE IF NOT EXISTS — so no existing table or feature is touched.
 *
 * Notification types covered:
 *   - new_lead          : a new client was registered
 *   - followup_due      : a scheduled follow-up/reminder is due today
 *   - missed_followup   : a scheduled follow-up/reminder is overdue
 *   - sale_completed    : a client was marked "Contracted" or moved to the
 *                         "Won" pipeline stage
 *   - status_changed    : a client's Final Status or Pipeline Stage changed
 */

/**
 * Creates the notifications table the first time it's needed.
 * Safe to call repeatedly.
 */
function ensure_notifications_table($conn) {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $sql = "CREATE TABLE IF NOT EXISTS `notifications` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `client_id` int(11) DEFAULT NULL,
        `company_id` int(11) DEFAULT NULL,
        `type` varchar(30) NOT NULL,
        `title` varchar(150) NOT NULL,
        `message` text DEFAULT NULL,
        `ref_date` date DEFAULT NULL,
        `is_read` tinyint(1) NOT NULL DEFAULT 0,
        `is_done` tinyint(1) NOT NULL DEFAULT 0,
        `snoozed_until` datetime DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`),
        KEY `client_id` (`client_id`),
        KEY `company_id` (`company_id`),
        KEY `type` (`type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    $conn->query($sql);

    $existing = $conn->query("SHOW COLUMNS FROM `notifications` LIKE 'is_done'");
    if ($existing && $existing->num_rows === 0) {
        $conn->query("ALTER TABLE `notifications` ADD COLUMN `is_done` tinyint(1) NOT NULL DEFAULT 0");
    }
    $existing2 = $conn->query("SHOW COLUMNS FROM `notifications` LIKE 'snoozed_until'");
    if ($existing2 && $existing2->num_rows === 0) {
        $conn->query("ALTER TABLE `notifications` ADD COLUMN `snoozed_until` datetime DEFAULT NULL");
    }
    $existing3 = $conn->query("SHOW COLUMNS FROM `notifications` LIKE 'company_id'");
    if ($existing3 && $existing3->num_rows === 0) {
        $conn->query("ALTER TABLE `notifications` ADD COLUMN `company_id` int(11) DEFAULT NULL");
    }
}

/**
 * Role-aware visibility filter for the `notifications` table.
 *   company_admin  -> every notification in the company
 *   sales_manager  -> notifications of self + own team, and only about clients
 *                     that are still in that scope
 *   sales_employee -> own notifications, and only about clients still assigned
 *                     to them (a lead reassigned away stops notifying the old owner)
 * Returns ['sql' => ' AND ...', 'types' => '...', 'params' => [...]]; the caller
 * binds its own `company_id = ?` first, then these.
 */
function notification_access_filter($conn) {
    if (!function_exists('access_user_filter')) {
        require_once __DIR__ . '/access_helper.php';
    }
    $filter = access_user_filter($conn, 'user_id');
    $ids = get_accessible_user_ids($conn);
    if ($ids === null) {
        return $filter; // admin: whole company
    }
    $safe = implode(',', array_map('intval', $ids));
    $company_id = (int) ($_SESSION['company_id'] ?? 0);
    return [
        'sql' => $filter['sql']
            . " AND (client_id IS NULL OR client_id = 0 OR client_id IN (SELECT id FROM clients WHERE company_id = ? AND user_id IN ($safe)))",
        'types' => $filter['types'] . 'i',
        'params' => array_merge($filter['params'], [$company_id]),
    ];
}

/** Canonical notification types + display icon/label, used by the UI. */
function get_notification_type_meta($type) {
    $meta = [
        'new_lead'        => ['label' => 'New Lead',        'icon' => 'fa-user-plus'],
        'followup_due'    => ['label' => 'Follow-up Due',   'icon' => 'fa-clock'],
        'sale_completed'  => ['label' => 'Sale Completed',  'icon' => 'fa-sack-dollar'],
        'status_changed'  => ['label' => 'Status Changed',  'icon' => 'fa-arrows-rotate'],
        'missed_followup' => ['label' => 'Missed Follow-up', 'icon' => 'fa-triangle-exclamation'],
        'followup_alert'  => ['label' => 'Follow-up Alert',  'icon' => 'fa-bell'],
    ];
    return $meta[$type] ?? ['label' => 'Notification', 'icon' => 'fa-bell'];
}

/**
 * Inserts a single notification for a user.
 */
function create_notification($conn, $user_id, $client_id, $type, $title, $message = null, $ref_date = null) {
    ensure_notifications_table($conn);
    $company_id = $_SESSION['company_id'] ?? null;

    // Never notify a user outside the current company.
    if ($company_id !== null) {
        $chk = $conn->prepare("SELECT id FROM users WHERE id = ? AND company_id = ?");
        $chk->bind_param("ii", $user_id, $company_id);
        $chk->execute();
        $same_company = $chk->get_result()->num_rows > 0;
        $chk->close();
        if (!$same_company) {
            return;
        }
    }

    $stmt = $conn->prepare(
        "INSERT INTO notifications (user_id, client_id, company_id, type, title, message, ref_date) VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("iiissss", $user_id, $client_id, $company_id, $type, $title, $message, $ref_date);
    $stmt->execute();
    $stmt->close();
}

/**
 * Inserts a notification only if an equivalent one doesn't already exist,
 * to avoid re-creating the same alert every time a page loads (used for
 * the "live computed" follow-up due / missed follow-up notifications).
 */
function create_notification_if_new($conn, $user_id, $client_id, $type, $title, $message, $ref_date) {
    ensure_notifications_table($conn);

    $company_id = (int) ($_SESSION['company_id'] ?? 0);
    $stmt = $conn->prepare(
        "SELECT id FROM notifications WHERE client_id = ? AND type = ? AND ref_date = ? AND user_id = ? AND company_id = ? LIMIT 1"
    );
    $stmt->bind_param("issii", $client_id, $type, $ref_date, $user_id, $company_id);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if (!$exists) {
        create_notification($conn, $user_id, $client_id, $type, $title, $message, $ref_date);
    }
}

/**
 * Scans clients with a due reminder date (same source of truth as the
 * Reminders page) and creates "Follow-up Due" / "Missed Follow-up"
 * notifications as needed. Purely additive — safe to call on every page
 * load for the logged-in user.
 */
/* Note: Scope comes from the logged-in user's role (access_helper.php); the legacy $is_manager argument is ignored. (applies to every function below taking $is_manager) */
function generate_followup_notifications($conn, $user_id = null, $is_manager = null) {
    if (!function_exists('get_active_reminders')) {
        require_once __DIR__ . '/reminder_helper.php';
    }
    ensure_notifications_table($conn);

    $reminders = get_active_reminders($conn);

    foreach ($reminders as $r) {
        $owner_id = $r['user_id'];
        $client_id = $r['id'];
        $client_name = $r['client_name'];
        $event_date = $r['event_date'];

        if ($r['reminder_status'] === 'today') {
            create_notification_if_new(
                $conn, $owner_id, $client_id, 'followup_due',
                'Follow-up Due',
                "Follow-up with \"{$client_name}\" is due today.",
                $event_date
            );
        } elseif ($r['reminder_status'] === 'overdue') {
            create_notification_if_new(
                $conn, $owner_id, $client_id, 'missed_followup',
                'Missed Follow-up',
                "Follow-up with \"{$client_name}\" was missed (was due {$event_date}).",
                $event_date
            );
        }
    }
}

/**
 * Returns the unread notification count within the caller's role scope.
 */
function get_unread_notification_count($conn, $user_id, $is_manager = null) {
    ensure_notifications_table($conn);
    if (!function_exists('access_user_filter')) {
        require_once __DIR__ . '/access_helper.php';
    }
    $company_id = $_SESSION['company_id'] ?? null;
    $filter = notification_access_filter($conn);

    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM notifications WHERE company_id = ? AND is_read = 0" . $filter['sql']);
    $types = 'i' . $filter['types'];
    $params = array_merge([$company_id], $filter['params']);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int) $row['c'];
}

/**
 * Fetches the notification list (most recent first) for the notification
 * center dropdown/panel.
 */
function get_notifications($conn, $user_id, $is_manager = null, $limit = 30) {
    ensure_notifications_table($conn);
    if (!function_exists('access_user_filter')) {
        require_once __DIR__ . '/access_helper.php';
    }
    $company_id = $_SESSION['company_id'] ?? null;
    $filter = notification_access_filter($conn);

    $stmt = $conn->prepare("SELECT * FROM notifications WHERE company_id = ?" . $filter['sql'] . " ORDER BY created_at DESC LIMIT ?");
    $types = 'i' . $filter['types'] . 'i';
    $params = array_merge([$company_id], $filter['params'], [$limit]);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/**
 * Marks a single notification as read, only if it is inside the caller's role scope
 * (admin: company, manager: own team, employee: own).
 */
function mark_notification_read($conn, $notification_id, $user_id, $is_manager = null) {
    ensure_notifications_table($conn);
    if (!function_exists('access_user_filter')) {
        require_once __DIR__ . '/access_helper.php';
    }
    $company_id = $_SESSION['company_id'] ?? null;
    $filter = notification_access_filter($conn);

    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND company_id = ?" . $filter['sql']);
    $types = 'ii' . $filter['types'];
    $params = array_merge([$notification_id, $company_id], $filter['params']);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    return $affected > 0;
}

/**
 * Marks every notification inside the caller's role scope as read.
 */
function mark_all_notifications_read($conn, $user_id, $is_manager = null) {
    ensure_notifications_table($conn);
    if (!function_exists('access_user_filter')) {
        require_once __DIR__ . '/access_helper.php';
    }
    $company_id = $_SESSION['company_id'] ?? null;
    $filter = notification_access_filter($conn);

    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE company_id = ? AND is_read = 0" . $filter['sql']);
    $types = 'i' . $filter['types'];
    $params = array_merge([$company_id], $filter['params']);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->close();
}

/**
 * Smart Alert & Reminder System
 * --------------------------------
 * Returns clients whose Follow-up Date (`event_date`) is today, are not
 * yet marked done, and are not currently snoozed, so they can be shown
 * as an automatic popup alert. Reuses `clients` as the single source of
 * truth (same as get_active_reminders) and the `notifications` table
 * purely to track per-client popup state (done / snoozed_until).
 */
function get_todays_followup_alerts($conn, $user_id = null, $is_manager = null) {
    ensure_notifications_table($conn);
    if (!function_exists('access_user_filter')) {
        require_once __DIR__ . '/access_helper.php';
    }
    $company_id = $_SESSION['company_id'] ?? null;
    $filter = access_user_filter($conn, 'user_id');

    $sql = "SELECT id, client_name, phone_number, event_title, event_date, event_time
            FROM clients
            WHERE event_date = CURDATE() AND company_id = ?" . $filter['sql'] . "
            ORDER BY event_time ASC";

    $stmt = $conn->prepare($sql);
    $types = 'i' . $filter['types'];
    $params = array_merge([$company_id], $filter['params']);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $clients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($clients)) {
        return [];
    }

    $client_ids = array_column($clients, 'id');
    $placeholders = implode(',', array_fill(0, count($client_ids), '?'));
    // Popup state (done / snoozed) is per person: a manager's action must not
    // hide an employee's alert, or the other way round.
    $viewer_id = (int) ($_SESSION['user_id'] ?? $user_id);
    $types = str_repeat('i', count($client_ids)) . 'ii';
    $state_params = array_merge($client_ids, [(int) $company_id, $viewer_id]);

    $stmt = $conn->prepare(
        "SELECT client_id, is_done, snoozed_until FROM notifications
         WHERE type = 'followup_alert' AND ref_date = CURDATE() AND client_id IN ($placeholders)
           AND company_id = ? AND user_id = ?"
    );
    $stmt->bind_param($types, ...$state_params);
    $stmt->execute();
    $states = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $state_by_client = [];
    foreach ($states as $s) {
        $state_by_client[$s['client_id']] = $s;
    }

    $now = new DateTime();
    $due = [];
    foreach ($clients as $c) {
        $state = $state_by_client[$c['id']] ?? null;

        if ($state && (int) $state['is_done'] === 1) {
            continue; // already handled today
        }
        if ($state && !empty($state['snoozed_until'])) {
            $snoozed_until = DateTime::createFromFormat('Y-m-d H:i:s', $state['snoozed_until']);
            if ($snoozed_until && $snoozed_until > $now) {
                continue; // still snoozed
            }
        }

        // Ensure a tracking row exists for this client's alert today.
        if (!$state) {
            create_notification(
                $conn, $user_id, $c['id'], 'followup_alert',
                'Follow-up Due Today',
                "Follow-up with \"{$c['client_name']}\" is due today.",
                $c['event_date']
            );
        }

        $due[] = [
            'client_id'      => (int) $c['id'],
            'client_name'    => $c['client_name'],
            'phone_number'   => $c['phone_number'],
            'follow_up_time' => $c['event_time'] ? substr($c['event_time'], 0, 5) : null,
            'follow_up_note' => $c['event_title'] ?: '',
        ];
    }

    return $due;
}

/**
 * Marks today's follow-up alert for a client as done, so it stops
 * popping up.
 */
function mark_followup_alert_done($conn, $client_id, $user_id, $is_manager = null) {
    ensure_notifications_table($conn);
    if (!function_exists('user_can_access_client')) {
        require_once __DIR__ . '/access_helper.php';
    }
    if (!user_can_access_client($conn, $client_id)) {
        return false;
    }
    $company_id = $_SESSION['company_id'] ?? null;

    $user_id = (int) ($_SESSION['user_id'] ?? $user_id);
    $stmt = $conn->prepare(
        "SELECT id FROM notifications WHERE client_id = ? AND type = 'followup_alert' AND ref_date = CURDATE() AND company_id = ? AND user_id = ? LIMIT 1"
    );
    $stmt->bind_param("iii", $client_id, $company_id, $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $stmt = $conn->prepare("UPDATE notifications SET is_done = 1, is_read = 1 WHERE id = ? AND company_id = ?");
        $stmt->bind_param("ii", $row['id'], $company_id);
        $stmt->execute();
        $stmt->close();
    } else {
        create_notification($conn, $user_id, $client_id, 'followup_alert', 'Follow-up Due Today', null, date('Y-m-d'));
        mark_followup_alert_done($conn, $client_id, $user_id);
    }
}

/**
 * Snoozes today's follow-up alert for a client by the given number of
 * minutes (default 10), so it stops popping up until then.
 */
function snooze_followup_alert($conn, $client_id, $user_id, $minutes = 10) {
    ensure_notifications_table($conn);
    if (!function_exists('user_can_access_client')) {
        require_once __DIR__ . '/access_helper.php';
    }
    if (!user_can_access_client($conn, $client_id)) {
        return null;
    }
    $company_id = $_SESSION['company_id'] ?? null;

    $snoozed_until = (new DateTime())->modify("+{$minutes} minutes")->format('Y-m-d H:i:s');

    $user_id = (int) ($_SESSION['user_id'] ?? $user_id);
    $stmt = $conn->prepare(
        "SELECT id FROM notifications WHERE client_id = ? AND type = 'followup_alert' AND ref_date = CURDATE() AND company_id = ? AND user_id = ? LIMIT 1"
    );
    $stmt->bind_param("iii", $client_id, $company_id, $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $stmt = $conn->prepare("UPDATE notifications SET snoozed_until = ? WHERE id = ? AND company_id = ?");
        $stmt->bind_param("sii", $snoozed_until, $row['id'], $company_id);
        $stmt->execute();
        $stmt->close();
    } else {
        create_notification($conn, $user_id, $client_id, 'followup_alert', 'Follow-up Due Today', null, date('Y-m-d'));
        snooze_followup_alert($conn, $client_id, $user_id, $minutes);
    }

    return $snoozed_until;
}
