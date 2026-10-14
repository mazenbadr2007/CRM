<?php
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../activity_logger.php';
require_once __DIR__ . '/../reminder_helper.php';
require_once __DIR__ . '/../notification_helper.php';
require_once __DIR__ . '/../lead_source_helper.php';
require_once __DIR__ . '/../access_helper.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id = $_POST['client_id'];
    $user_id = $_SESSION['user_id'] ?? null;
    // Role-based scope (access_helper.php): admin = company, manager = own team, employee = own assigned clients.
    $company_id = $_SESSION['company_id'] ?? null;

    // Capture the client's current data before it's overwritten, so we can
    // detect exactly what changed and log it to the activity timeline.
    $client_id = (int) $client_id;
    $old_client_data = fetch_accessible_client($conn, $client_id, false);
    if ($old_client_data === null) {
        // Not in this user's scope (admin: company, manager: team, employee: own).
        header("Location: ../../public/clients_dashboard.php");
        exit();
    }
    $client_name = $_POST['client_name'];
    $phone_number = $_POST['phone_number'];
    $project_location = $_POST['project_location'];
    $project_name = $_POST['project_name'];
    $unit_area = $_POST['unit_area'] ?: null;
    $the_role = $_POST['the_role'];
    $receiving = $_POST['receiving'];
    $unit_price = $_POST['unit_price'] ?: null;
    $down_payment = $_POST['down_payment'] ?: null;
    $installment = $_POST['installment'];
    $installment_amount = $_POST['installment_amount'] ?: null;
    $visit = $_POST['visit'];
    $call_outcome = $_POST['call_outcome'];
    $visit_outcome = $_POST['visit_outcome'];
    $final_status = $_POST['final_status'];
    $event_title = $_POST['event_title'];
    $event_date = !empty($_POST['event_date']) ? $_POST['event_date'] : null;
    $event_time = !empty($_POST['event_time']) ? $_POST['event_time'] : null;
    $note = $_POST['note'];
    $lead_source = normalize_lead_source($_POST['lead_source'] ?? '');
    $sql = "UPDATE clients SET 
                client_name = ?, 
                phone_number = ?, 
                project_location = ?, 
                project_name = ?, 
                unit_area = ?, 
                the_role = ?, 
                receiving = ?, 
                unit_price = ?, 
                down_payment = ?, 
                installment = ?, 
                installment_amount = ?, 
                visit = ?, 
                call_outcome = ?, 
                visit_outcome = ?, 
                final_status = ?, 
                event_title = ?,
                event_date = ?, 
                event_time = ?, 
                note = ?,
                lead_source = ?
            WHERE id = ? AND company_id = ?" . access_user_ids_sql($conn, 'user_id') . "";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "ssssissddsdsssssssssii", 
        $client_name,
        $phone_number,
        $project_location,
        $project_name,
        $unit_area,
        $the_role,
        $receiving,
        $unit_price,
        $down_payment,
        $installment,
        $installment_amount,
        $visit,
        $call_outcome,
        $visit_outcome,
        $final_status,
        $event_title,
        $event_date,
        $event_time,
        $note,
        $lead_source,
        $client_id,
        $company_id
    );
    if ($stmt->execute()) {

        if ($old_client_data !== null) {
            $new_client_data = [
                'client_name' => $client_name,
                'phone_number' => $phone_number,
                'project_location' => $project_location,
                'project_name' => $project_name,
                'unit_area' => $unit_area,
                'the_role' => $the_role,
                'receiving' => $receiving,
                'unit_price' => $unit_price,
                'down_payment' => $down_payment,
                'installment' => $installment,
                'installment_amount' => $installment_amount,
                'visit' => $visit,
                'call_outcome' => $call_outcome,
                'visit_outcome' => $visit_outcome,
                'final_status' => $final_status,
                'event_title' => $event_title,
                'event_date' => $event_date,
                'event_time' => $event_time,
                'note' => $note,
                'lead_source' => $lead_source,
            ];
            $current_user_id = $_SESSION['user_id'] ?? null;
            $current_username = $_SESSION['username'] ?? 'Unknown';
            log_client_activity_diff($conn, $client_id, $current_user_id, $current_username, $old_client_data, $new_client_data);

            // Notification Center: Status Changed / Sale Completed.
            $old_final_status = trim((string) ($old_client_data['final_status'] ?? ''));
            $new_final_status = trim((string) $final_status);
            $notify_owner_id = $old_client_data['user_id'] ?? $current_user_id;
            if ($new_final_status !== '' && $old_final_status !== $new_final_status) {
                create_notification(
                    $conn, $notify_owner_id, $client_id, 'status_changed',
                    'Status Changed',
                    "\"{$client_name}\"'s status changed from \"" . ($old_final_status !== '' ? $old_final_status : 'N/A') . "\" to \"{$new_final_status}\"."
                );
                if ($new_final_status === 'Contracted') {
                    create_notification(
                        $conn, $notify_owner_id, $client_id, 'sale_completed',
                        'Sale Completed',
                        "Sale completed for \"{$client_name}\"."
                    );
                }
            }

            // Reminder history: record whenever the reminder date/time was
            // set, changed, or cleared. Additive only — does not affect the
            // Follow-up page or the update flow above.
            $old_event_date = $old_client_data['event_date'] ?? null;
            $old_event_time = $old_client_data['event_time'] ?? null;
            if ($old_event_date !== $event_date || $old_event_time !== $event_time) {
                if (!empty($event_date)) {
                    $reminder_status = (!empty($old_event_date)) ? 'rescheduled' : 'created';
                    log_reminder_event($conn, $client_id, $current_user_id, $event_date, $event_time, $reminder_status, $event_title);
                } elseif (!empty($old_event_date)) {
                    log_reminder_event($conn, $client_id, $current_user_id, $old_event_date, $old_event_time, 'cleared', $event_title);
                }
            }
        }

        header("Location: ../../public/clients_dashboard.php?status=update_success");
    } else {
        
        echo "Error updating record: " . $conn->error;
    }

    $stmt->close();
    $conn->close();
}
?>