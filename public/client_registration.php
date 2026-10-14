<?php

require_once __DIR__ . '/../src/auth/auth_check.php';
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/lead_source_helper.php';
require_once __DIR__ . '/../src/access_helper.php';



$is_edit_mode = false;
$client_data = [];


if (isset($_GET['id']) && !empty($_GET['id'])) {
    $is_edit_mode = true;
    $client_id = (int) $_GET['id'];
    $user_id = $_SESSION['user_id'];
    // Role-based scope (access_helper.php): admin = company, manager = own team, employee = own assigned clients.
    $company_id = $_SESSION['company_id'] ?? null;

    $client_data = fetch_accessible_client($conn, $client_id, false);
    if (!$client_data) {
        header('Location: clients_dashboard.php');
        exit;
    }
}
require_once __DIR__ . '/../src/templates/header.php';
?>
<link rel="stylesheet" href="css/client_registration.css">

<div class="container-fluid">
    <div class="card form-card">
        <div class="card-header text-center">

            <?php if ($is_edit_mode): ?>
                <h2 class="card-title fw-bold" data-translate-key="update_client_title">Update Client Details</h2>
                <p class="card-subtitle text-muted" data-translate-key="update_client_subtitle">Modify the client's
                    information below.</p>
            <?php else: ?>
                <h2 class="card-title fw-bold" data-translate-key="client_reg_title">Client Registration</h2>
                <p class="card-subtitle text-muted" data-translate-key="client_reg_subtitle">Enter the details of the new
                    client below.</p>
            <?php endif; ?>
        </div>
        <div class="card-body">

            <form
                action="<?php echo $is_edit_mode ? '../src/actions/handle_client_update.php' : '../src/actions/handle_client_registration.php'; ?>"
                method="POST">


                <?php if ($is_edit_mode): ?>
                    <input type="hidden" name="client_id" value="<?php echo htmlspecialchars($client_data['id']); ?>">
                <?php endif; ?>


                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label for="client_name" class="form-label" data-translate-key="client_name" required>Client
                            Name</label>
                        <input type="text" class="form-control" name="client_name" required
                            value="<?php echo htmlspecialchars($client_data['client_name'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="phone_number" class="form-label" data-translate-key="phone_number" required>Phone
                            Number</label>
                        <input type="tel" class="form-control" name="phone_number" required
                            value="<?php echo htmlspecialchars($client_data['phone_number'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="project_location" class="form-label" data-translate-key="project_location"
                            required>Project Location</label>
                        <select class="form-select" name="project_location" required>
                            <?php
                            $locations = ['New Cairo', 'El Shorouk City', 'Badr City', 'Nasr City'];
                            foreach ($locations as $location) {
                                $selected = (isset($client_data['project_location']) && $client_data['project_location'] == $location) ? 'selected' : '';
                                echo "<option value=\"$location\" $selected>" . T($location) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label for="lead_source" class="form-label" data-translate-key="lead_source" required>Lead
                            Source</label>
                        <select class="form-select" name="lead_source" id="lead_source" required>
                            <?php
                            foreach (LEAD_SOURCES as $source) {
                                $selected = (isset($client_data['lead_source']) && $client_data['lead_source'] == $source) ? 'selected' : '';
                                echo "<option value=\"$source\" $selected>" . T($source) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label for="project_name" class="form-label" data-translate-key="project_name" required>Project
                            Name</label>
                        <input type="text" class="form-control" name="project_name"
                            value="<?php echo htmlspecialchars($client_data['project_name'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="unit_area" class="form-label" data-translate-key="unit_area" required>Unit Area
                            (m²)</label>
                        <input type="number" class="form-control" name="unit_area"
                            value="<?php echo htmlspecialchars($client_data['unit_area'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="the_role" class="form-label" data-translate-key="the_role" required>The Role</label>
                        <select class="form-select" name="the_role" required>
                            <?php
                            $roles = ['Ground Floor', 'Duplex', 'Typical Floor', 'Roof'];
                            foreach ($roles as $role) {
                                $selected = (isset($client_data['the_role']) && $client_data['the_role'] == $role) ? 'selected' : '';
                                echo "<option value=\"$role\" $selected>" . T($role) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label for="receiving" class="form-label" data-translate-key="receiving"
                            required>Receiving</label>
                        <select class="form-select" name="receiving" required>
                            <?php
                            $receivings = ['Ready to Move', 'Under Construction'];
                            foreach ($receivings as $item) {
                                $selected = (isset($client_data['receiving']) && $client_data['receiving'] == $item) ? 'selected' : '';
                                echo "<option value=\"$item\" $selected>" . T($item) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="unit_price" class="form-label" data-translate-key="unit_price" required>Unit
                            Price</label>
                        <input type="number" step="0.01" class="form-control" name="unit_price"
                            value="<?php echo htmlspecialchars($client_data['unit_price'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="down_payment" class="form-label" data-translate-key="down_payment" required>Down
                            Payment</label>
                        <input type="number" step="0.01" class="form-control" name="down_payment"
                            value="<?php echo htmlspecialchars($client_data['down_payment'] ?? ''); ?>" required>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label for="installment" class="form-label" data-translate-key="installment"
                            required>Installment</label>
                        <select class="form-select" name="installment" required>
                            <?php
                            $installments = ['Monthly', 'Quarterly', 'Semi-annually', 'Annually'];
                            foreach ($installments as $item) {
                                $selected = (isset($client_data['installment']) && $client_data['installment'] == $item) ? 'selected' : '';
                                echo "<option value=\"$item\" $selected>" . T($item) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="installment_amount" class="form-label" data-translate-key="installment_amount"
                            required>Installment Amount</label>
                        <input type="number" step="0.01" class="form-control" name="installment_amount"
                            value="<?php echo htmlspecialchars($client_data['installment_amount'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="visit" class="form-label" data-translate-key="visit" required>Visit</label>
                        <select class="form-select" name="visit" required>
                            <?php
                            $visits = ['Location', 'Company', 'Call', 'Location & Company'];
                            foreach ($visits as $item) {
                                $selected = (isset($client_data['visit']) && $client_data['visit'] == $item) ? 'selected' : '';
                                echo "<option value=\"$item\" $selected>" . T($item) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label for="call_outcome" class="form-label" data-translate-key="call_outcome">Call
                            Outcome</label>
                        <select class="form-select" name="call_outcome">
                            <?php
                            $outcomes = ['Interested', 'Follow-up', 'Not Interested'];
                            foreach ($outcomes as $item) {
                                $selected = (isset($client_data['call_outcome']) && $client_data['call_outcome'] == $item) ? 'selected' : '';
                                echo "<option value=\"$item\" $selected>" . T($item) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="visit_outcome" class="form-label" data-translate-key="visit_outcome">Visit
                            Outcome</label>
                        <select class="form-select" name="visit_outcome">
                            <?php
                            $v_outcomes = ['Follow-up', 'Interested', 'Rejected', 'Contracted'];
                            foreach ($v_outcomes as $item) {
                                $selected = (isset($client_data['visit_outcome']) && $client_data['visit_outcome'] == $item) ? 'selected' : '';
                                echo "<option value=\"$item\" $selected>" . T($item) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="final_status" class="form-label" data-translate-key="final_status">Final
                            Status</label>
                        <select class="form-select" name="final_status">
                            <?php
                            $statuses = ['Follow-up', 'Interested', 'Rejected', 'Contracted', 'Not Interested'];
                            foreach ($statuses as $item) {
                                $selected = (isset($client_data['final_status']) && $client_data['final_status'] == $item) ? 'selected' : '';
                                echo "<option value=\"$item\" $selected>" . T($item) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label for="event_title" class="form-label" data-translate-key="event_title_cal" required>Event
                            Title (for Calendar)</label>
                        <input type="text" class="form-control" name="event_title" placeholder="e.g., Follow-up Call"
                            data-translate-key="follow_up_call_placeholder"
                            value="<?php echo htmlspecialchars($client_data['event_title'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="event_date" class="form-label" data-translate-key="event_date" required>Event
                            Date</label>
                        <input type="date" class="form-control" name="event_date"
                            value="<?php echo htmlspecialchars($client_data['event_date'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="event_time" class="form-label" data-translate-key="event_time" required>Event
                            Time</label>
                        <input type="time" class="form-control" name="event_time"
                            value="<?php echo htmlspecialchars($client_data['event_time'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <label for="note" class="form-label" data-translate-key="note" required>Note</label>
                        <textarea class="form-control" name="note" rows="4"
                            required><?php echo htmlspecialchars($client_data['note'] ?? ''); ?></textarea>
                    </div>
                </div>
                <hr class="my-4">
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <button type="submit" class="btn btn-primary btn-lg px-5"
                        data-translate-key="<?php echo $is_edit_mode ? 'update_btn' : 'save_client_btn'; ?>" required>
                        <?php echo $is_edit_mode ? 'Update Client' : 'Save Client'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
require_once __DIR__ . '/../src/templates/footer.php';
?>