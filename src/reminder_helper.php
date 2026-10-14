<?php
/**
 * Reminder System helper.
 *
 * Builds on the `event_date` / `event_time` / `event_title` columns that
 * already exist on `clients` (used today by Client Registration and the
 * Calendar page as the follow-up date/time). This file does not add or
 * rename any column on `clients` and does not touch the Follow-up page.
 *
 * It adds one new, self-healing table (`client_reminders`) — created the
 * same way `client_activity_log` and `pipeline_stage` are, via
 * CREATE TABLE IF NOT EXISTS — to record reminder history (created /
 * completed / dismissed / snoozed), so the Reminders page can show a
 * history log in addition to live upcoming/overdue reminders computed
 * straight from `clients`.
 */

/**
 * Creates the reminder history table the first time it's needed.
 * Safe to call repeatedly.
 */
function ensure_reminder_history_table($conn) {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $sql = "CREATE TABLE IF NOT EXISTS `client_reminders` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `client_id` int(11) NOT NULL,
        `user_id` int(11) DEFAULT NULL,
        `company_id` int(11) DEFAULT NULL,
        `reminder_date` date DEFAULT NULL,
        `reminder_time` time DEFAULT NULL,
        `status` varchar(20) NOT NULL DEFAULT 'active',
        `note` text DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `resolved_at` timestamp NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `client_id` (`client_id`),
        KEY `user_id` (`user_id`),
        KEY `company_id` (`company_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    $conn->query($sql);

    $check = $conn->query("SHOW COLUMNS FROM `client_reminders` LIKE 'company_id'");
    if ($check && $check->num_rows === 0) {
        $conn->query("ALTER TABLE `client_reminders` ADD COLUMN `company_id` int(11) DEFAULT NULL");
    }
}

/**
 * Records a history entry (created / completed / dismissed / snoozed) for a
 * client's reminder. Purely additive logging; never required for the
 * live overdue/upcoming computation below.
 */
function log_reminder_event($conn, $client_id, $user_id, $reminder_date, $reminder_time, $status, $note = null) {
    ensure_reminder_history_table($conn);
    $company_id = $_SESSION['company_id'] ?? null;

    // History can only be written for a client inside the caller's role scope
    // (admin: company, manager: own team, employee: own assigned clients).
    if (!function_exists('user_can_access_client')) {
        require_once __DIR__ . '/access_helper.php';
    }
    if (!user_can_access_client($conn, $client_id)) {
        return;
    }

    $stmt = $conn->prepare(
        "INSERT INTO client_reminders (client_id, user_id, company_id, reminder_date, reminder_time, status, note) VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $reminder_time = $reminder_time !== '' ? $reminder_time : null;
    $reminder_date = $reminder_date !== '' ? $reminder_date : null;
    $stmt->bind_param("iiissss", $client_id, $user_id, $company_id, $reminder_date, $reminder_time, $status, $note);
    $stmt->execute();
    $stmt->close();
}

/**
 * Fetches all clients inside the caller's role scope (admin: company, manager: own team,
 * employee: own assigned clients) that currently
 * have a reminder date set (event_date on `clients`), along with a computed
 * status: 'overdue', 'today', or 'upcoming'.
 *
 * This reads live from `clients` so it always reflects the current
 * event_date/time set on Client Registration — the same source of truth
 * the Calendar page uses — rather than a separate, easily-stale copy.
 */
/* Note: Scope comes from the logged-in user's role (access_helper.php); the legacy $is_manager argument is ignored. */
function get_active_reminders($conn, $user_id = null, $is_manager = null) {
    if (!function_exists('access_user_filter')) {
        require_once __DIR__ . '/access_helper.php';
    }
    $company_id = $_SESSION['company_id'] ?? null;
    $filter = access_user_filter($conn, 'user_id');
    $sql = "SELECT id, client_name, phone_number, project_name, event_title, event_date, event_time, user_id
            FROM clients
            WHERE event_date IS NOT NULL AND company_id = ?" . $filter['sql'] . "
            ORDER BY event_date ASC, event_time ASC";

    $stmt = $conn->prepare($sql);
    $types = 'i' . $filter['types'];
    $params = array_merge([$company_id], $filter['params']);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $now = new DateTime();
    $today = $now->format('Y-m-d');

    foreach ($rows as &$row) {
        $event_date = $row['event_date'];
        $event_time = !empty($row['event_time']) ? $row['event_time'] : '23:59:59';
        $event_datetime = DateTime::createFromFormat('Y-m-d H:i:s', $event_date . ' ' . $event_time);

        if ($event_datetime === false) {
            $row['reminder_status'] = 'upcoming';
            continue;
        }

        if ($event_datetime < $now) {
            $row['reminder_status'] = 'overdue';
        } elseif ($event_date === $today) {
            $row['reminder_status'] = 'today';
        } else {
            $row['reminder_status'] = 'upcoming';
        }
    }
    unset($row);

    return $rows;
}

/**
 * Returns just the counts (overdue / today / upcoming) — used for the
 * sidebar notification badge without pulling full row data on every page.
 */
function get_reminder_counts($conn, $user_id = null, $is_manager = null) {
    $reminders = get_active_reminders($conn);

    $counts = ['overdue' => 0, 'today' => 0, 'upcoming' => 0];
    foreach ($reminders as $r) {
        $counts[$r['reminder_status']]++;
    }
    $counts['total_due'] = $counts['overdue'] + $counts['today'];

    return $counts;
}

/**
 * Fetches the reminder history log (most recent first), optionally scoped
 * to a single client.
 */
function get_reminder_history($conn, $user_id = null, $is_manager = null, $client_id = null, $limit = 100) {
    ensure_reminder_history_table($conn);
    if (!function_exists('access_user_filter')) {
        require_once __DIR__ . '/access_helper.php';
    }
    $company_id = $_SESSION['company_id'] ?? null;
    $filter = access_user_filter($conn, 'c.user_id');

    $sql = "SELECT cr.*, c.client_name, c.phone_number
            FROM client_reminders cr
            LEFT JOIN clients c ON c.id = cr.client_id
            WHERE cr.company_id = ?" . $filter['sql'];
    $types = "i" . $filter['types'];
    $params = array_merge([$company_id], $filter['params']);
    if ($client_id !== null) {
        $sql .= " AND cr.client_id = ?";
        $types .= "i";
        $params[] = $client_id;
    }
    $sql .= " ORDER BY cr.created_at DESC LIMIT ?";
    $types .= "i";
    $params[] = $limit;

    $stmt = $conn->prepare($sql);
    if ($types !== "") {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows;
}
