<?php
/**
 * Activity Timeline logger.
 *
 * Records every important client action (creation, updates, calls, visits,
 * status changes, notes, follow-ups) into the `client_activity_log` table
 * so it can be displayed as a chronological timeline per client.
 *
 * This file is self-contained and does not touch any existing table.
 */

/**
 * Creates the activity log table the first time it's needed.
 * Safe to call repeatedly (CREATE TABLE IF NOT EXISTS).
 */
function ensure_activity_log_table($conn) {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $sql = "CREATE TABLE IF NOT EXISTS `client_activity_log` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `client_id` int(11) NOT NULL,
        `user_id` int(11) DEFAULT NULL,
        `company_id` int(11) DEFAULT NULL,
        `employee_name` varchar(100) DEFAULT NULL,
        `action_type` varchar(50) NOT NULL,
        `description` text DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `client_id` (`client_id`),
        KEY `user_id` (`user_id`),
        KEY `company_id` (`company_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    $conn->query($sql);

    $check = $conn->query("SHOW COLUMNS FROM `client_activity_log` LIKE 'company_id'");
    if ($check && $check->num_rows === 0) {
        $conn->query("ALTER TABLE `client_activity_log` ADD COLUMN `company_id` int(11) DEFAULT NULL");
    }
}

/**
 * Inserts a single activity log entry.
 */
function log_activity($conn, $client_id, $user_id, $employee_name, $action_type, $description) {
    ensure_activity_log_table($conn);
    $company_id = $_SESSION['company_id'] ?? null;

    $stmt = $conn->prepare(
        "INSERT INTO client_activity_log (client_id, user_id, company_id, employee_name, action_type, description) VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("iiisss", $client_id, $user_id, $company_id, $employee_name, $action_type, $description);
    $stmt->execute();
    $stmt->close();
}

/**
 * Normalizes a value coming either from the DB or from $_POST so that
 * "" and null are treated as equal, and whitespace/precision differences
 * (e.g. "122" vs "122.00") don't produce false-positive "changed" results.
 */
function _activity_norm($value) {
    if ($value === null) {
        return '';
    }
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }
    if (is_numeric($value)) {
        // Normalize numeric formatting (e.g. 122 vs 122.00)
        return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');
    }
    return $value;
}

function _activity_changed($old, $new) {
    return _activity_norm($old) !== _activity_norm($new);
}

/**
 * Records "Client Created" when a new client record is added.
 */
function log_client_created($conn, $client_id, $user_id, $employee_name, $client_name) {
    $description = "New client \"" . $client_name . "\" was added to the system.";
    log_activity($conn, $client_id, $user_id, $employee_name, 'client_created', $description);
}

/**
 * Compares the client's data before and after an update, and automatically
 * logs one entry per meaningful change detected (status, phone call, visit,
 * follow-up scheduling, notes, or a generic "client updated" entry for any
 * other field).
 *
 * $old and $new are associative arrays using the `clients` table column
 * names as keys.
 */
function log_client_activity_diff($conn, $client_id, $user_id, $employee_name, $old, $new) {

    $visit_is_physical = in_array($new['visit'] ?? '', ['Location', 'Company', 'Location & Company'], true);

    $handled_completion_via_outcome = false;

    // 1. Status changed
    if (_activity_changed($old['final_status'] ?? '', $new['final_status'] ?? '') && !empty(trim((string)($new['final_status'] ?? '')))) {
        $from = !empty(trim((string)($old['final_status'] ?? ''))) ? $old['final_status'] : 'N/A';
        $description = "Status changed from \"{$from}\" to \"{$new['final_status']}\".";
        log_activity($conn, $client_id, $user_id, $employee_name, 'status_changed', $description);
    }

    // 2. Phone Call (call outcome recorded/changed)
    if (_activity_changed($old['call_outcome'] ?? '', $new['call_outcome'] ?? '') && !empty(trim((string)($new['call_outcome'] ?? '')))) {
        $description = "Phone call logged — outcome: \"{$new['call_outcome']}\".";
        log_activity($conn, $client_id, $user_id, $employee_name, 'phone_call', $description);
    }

    // 3. Visit Completed (visit outcome recorded/changed)
    if (_activity_changed($old['visit_outcome'] ?? '', $new['visit_outcome'] ?? '') && !empty(trim((string)($new['visit_outcome'] ?? '')))) {
        $description = "Visit outcome recorded: \"{$new['visit_outcome']}\".";
        log_activity($conn, $client_id, $user_id, $employee_name, 'visit_completed', $description);
        $handled_completion_via_outcome = true;
    }

    // 4. Follow-up / Visit scheduling, based on the calendar event fields
    $old_event_date = trim((string)($old['event_date'] ?? ''));
    $new_event_date = trim((string)($new['event_date'] ?? ''));
    $event_fields_changed = _activity_changed($old['event_date'] ?? '', $new['event_date'] ?? '')
        || _activity_changed($old['event_time'] ?? '', $new['event_time'] ?? '')
        || _activity_changed($old['event_title'] ?? '', $new['event_title'] ?? '');

    if ($event_fields_changed) {
        if ($old_event_date === '' && $new_event_date !== '') {
            // A brand new event/follow-up was scheduled
            $title = !empty($new['event_title']) ? $new['event_title'] : 'Follow-up';
            $when = $new_event_date . (!empty($new['event_time']) ? ' at ' . $new['event_time'] : '');
            if ($visit_is_physical) {
                log_activity($conn, $client_id, $user_id, $employee_name, 'visit_scheduled', "Visit scheduled for {$when} — \"{$title}\".");
            } else {
                log_activity($conn, $client_id, $user_id, $employee_name, 'followup_created', "Follow-up scheduled for {$when} — \"{$title}\".");
            }
        } elseif ($old_event_date !== '' && $new_event_date === '') {
            // The scheduled event was cleared -> treat as completed
            if (!$handled_completion_via_outcome) {
                $when = $old_event_date . (!empty($old['event_time']) ? ' at ' . $old['event_time'] : '');
                if ($visit_is_physical) {
                    log_activity($conn, $client_id, $user_id, $employee_name, 'visit_completed', "Scheduled visit for {$when} was completed.");
                } else {
                    log_activity($conn, $client_id, $user_id, $employee_name, 'followup_completed', "Follow-up scheduled for {$when} was completed.");
                }
            }
        } elseif ($old_event_date !== '' && $new_event_date !== '') {
            // Rescheduled
            $title = !empty($new['event_title']) ? $new['event_title'] : 'Follow-up';
            $when = $new_event_date . (!empty($new['event_time']) ? ' at ' . $new['event_time'] : '');
            if ($visit_is_physical) {
                log_activity($conn, $client_id, $user_id, $employee_name, 'visit_scheduled', "Visit rescheduled to {$when} — \"{$title}\".");
            } else {
                log_activity($conn, $client_id, $user_id, $employee_name, 'followup_created', "Follow-up rescheduled to {$when} — \"{$title}\".");
            }
        }
    }

    // 5. Notes Added
    if (_activity_changed($old['note'] ?? '', $new['note'] ?? '') && !empty(trim((string)($new['note'] ?? '')))) {
        $excerpt = trim((string) $new['note']);
        if (function_exists('mb_strlen')) {
            if (mb_strlen($excerpt) > 140) {
                $excerpt = mb_substr($excerpt, 0, 140) . '…';
            }
        } elseif (strlen($excerpt) > 140) {
            $excerpt = substr($excerpt, 0, 140) . '…';
        }
        log_activity($conn, $client_id, $user_id, $employee_name, 'notes_added', "Note added: \"{$excerpt}\"");
    }

    // 6. Generic "Client Updated" for any other core field edits
    $generic_fields = [
        'client_name'  => 'Client Name',
        'phone_number' => 'Phone Number',
        'project_location' => 'Project Location',
        'project_name' => 'Project Name',
        'unit_area' => 'Unit Area',
        'the_role' => 'Role',
        'receiving' => 'Receiving',
        'unit_price' => 'Unit Price',
        'down_payment' => 'Down Payment',
        'installment' => 'Installment',
        'installment_amount' => 'Installment Amount',
        'visit' => 'Visit Type',
        'lead_source' => 'Lead Source',
    ];
    $changed_labels = [];
    foreach ($generic_fields as $key => $label) {
        if (_activity_changed($old[$key] ?? '', $new[$key] ?? '')) {
            $changed_labels[] = $label;
        }
    }
    if (!empty($changed_labels)) {
        $description = "Client details updated (" . implode(', ', $changed_labels) . ").";
        log_activity($conn, $client_id, $user_id, $employee_name, 'client_updated', $description);
    }
}

/**
 * Records a Sales Pipeline stage change (drag-and-drop on the Kanban board).
 */
function log_pipeline_stage_changed($conn, $client_id, $user_id, $employee_name, $old_stage, $new_stage) {
    $from = !empty(trim((string) $old_stage)) ? $old_stage : 'N/A';
    $description = "Pipeline stage moved from \"{$from}\" to \"{$new_stage}\".";
    log_activity($conn, $client_id, $user_id, $employee_name, 'pipeline_stage_changed', $description);
}
