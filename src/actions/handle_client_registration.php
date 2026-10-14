<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../activity_logger.php';
require_once __DIR__ . '/../reminder_helper.php';
require_once __DIR__ . '/../notification_helper.php';
require_once __DIR__ . '/../lead_source_helper.php';
require_once __DIR__ . '/../lead_distribution_helper.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $client_name = trim($_POST['client_name']);
    $phone_number = trim($_POST['phone_number']);
    $project_location = $_POST['project_location'];
    $lead_source = normalize_lead_source($_POST['lead_source'] ?? '');
    $project_name = trim($_POST['project_name']);
    $unit_area = !empty($_POST['unit_area']) ? (int)$_POST['unit_area'] : null;
    $the_role = $_POST['the_role'];
    $receiving = $_POST['receiving'];
    $unit_price = !empty($_POST['unit_price']) ? (float)$_POST['unit_price'] : null;
    $down_payment = !empty($_POST['down_payment']) ? (float)$_POST['down_payment'] : null;
    $installment = $_POST['installment'];
    $installment_amount = !empty($_POST['installment_amount']) ? (float)$_POST['installment_amount'] : null;
    $visit = $_POST['visit'];
    $call_outcome = $_POST['call_outcome'];
    $visit_outcome = $_POST['visit_outcome'];
    $final_status = trim($_POST['final_status']);
    $event_title = trim($_POST['event_title']);
    $event_date = !empty($_POST['event_date']) ? $_POST['event_date'] : null;
    $event_time = !empty($_POST['event_time']) ? $_POST['event_time'] : null;
    $note = trim($_POST['note']);
    $user_id = $_SESSION['user_id'];
    $company_id = $_SESSION['company_id'] ?? null;

    if (empty($client_name) || empty($phone_number)) {
        header("Location: ../../public/client_registration.php?error=required_fields");
        exit();
    }

    // Smart Lead Distribution: the new lead is owned by whoever the round robin
    // picks (fewest open leads, then rotation) - decided in the same transaction
    // as the INSERT so simultaneous registrations cannot collide
    // (assign_new_lead_owner opens the transaction; commit/rollback below).
    $owner = assign_new_lead_owner($conn, $company_id, $user_id, current_user_role());
    $owner_id = $owner['user_id'];

    $sql = "INSERT INTO clients (company_id, user_id, client_name, phone_number, project_location, project_name, unit_area, the_role, receiving, unit_price, down_payment, installment, installment_amount, visit, call_outcome, visit_outcome, final_status, event_title, event_date, event_time, note, lead_source) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisssisssddssssssssss" . "s", 
        $company_id, $owner_id, $client_name, $phone_number, $project_location, $project_name, 
        $unit_area, $the_role, $receiving, $unit_price, $down_payment, 
        $installment, $installment_amount, $visit, $call_outcome, $visit_outcome, 
        $final_status, $event_title, $event_date, $event_time, $note, $lead_source
    );

    $inserted = false;
    try {
        $inserted = $stmt->execute();
    } catch (Throwable $e) {
        $inserted = false;   // e.g. blocked by a database guard
    }

    if ($inserted) {
        $new_client_id = $stmt->insert_id;
        $conn->commit();
        $employee_name = $_SESSION['username'] ?? 'Unknown';
        log_client_created($conn, $new_client_id, $user_id, $employee_name, $client_name);

        if ($owner_id === (int) $user_id) {
            create_notification(
                $conn, $user_id, $new_client_id, 'new_lead',
                'New Lead',
                "New lead \"{$client_name}\" was added by {$employee_name}."
            );
        } else {
            // Auto-assigned to someone else: timeline entry + notify the new owner.
            announce_lead_assignment($conn, $company_id, $new_client_id, $client_name, $owner_id, $user_id, $employee_name, $owner['mode']);
        }

        if (!empty($event_date)) {
            log_reminder_event($conn, $new_client_id, $user_id, $event_date, $event_time, 'created', $event_title);
        }

        header("Location: ../../public/clients_dashboard.php?status=client_added");
        exit();
    } else {
         $conn->rollback();
         header("Location: ../../public/client_registration.php?error=add_failed");
         exit();
    }
}
?>