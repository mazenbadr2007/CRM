<?php
require_once __DIR__ . '/../src/auth/auth_check.php';
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/access_helper.php';

$user_id = $_SESSION['user_id'];
// Role-based scope (access_helper.php): company_admin = whole company,
// sales_manager = own + own team's leads, sales_employee = own assigned leads only.
$company_id = $_SESSION['company_id'] ?? null;
$clients = list_accessible_clients($conn, false);

require_once __DIR__ . '/../src/templates/header.php';
?>
<link rel="stylesheet" href="css/follow_ups.css">

<div class="container-fluid">
    <div class="page-header">
        <h1 class="page-title" data-translate-key="follow_ups_list_title">Follow-ups List</h1>
        <p class="page-subtitle" data-translate-key="follow_ups_list_subtitle">A simple list of all clients and their current status.</p>
    </div>

    <div class="client-list-container">
        <?php if (empty($clients)): ?>
            <div class="card text-center p-5">
                <p class="text-muted fs-4" data-translate-key="no_clients_found">No clients to display.</p>
            </div>
        <?php else: ?>
            <?php foreach ($clients as $client): ?>
                <div class="client-list-item">
                    <div class="client-info">
                        <h5 class="client-name"><?php echo htmlspecialchars($client['client_name']); ?></h5>
                        <p class="client-phone text-muted mb-0"><?php echo htmlspecialchars($client['phone_number']); ?></p>
                    </div>

                    <div class="client-project">
                        <i class="fa-solid fa-building me-2 text-muted"></i>
                        <span><?php echo htmlspecialchars($client['project_name']); ?></span>
                    </div>

                    <div class="client-status">
                        <?php
                            $status = !empty($client['final_status']) ? $client['final_status'] : 'Follow-up';
                            $badge_class = 'bg-secondary';
                            if ($status == 'Interested') $badge_class = 'bg-success';
                            if ($status == 'Follow-up') $badge_class = 'bg-warning text-dark';
                            if ($status == 'Not Interested') $badge_class = 'bg-danger';
                            if ($status == 'Contracted') $badge_class = 'bg-primary';
                        ?>
                        <span class="badge rounded-pill <?php echo $badge_class; ?> status-badge"><?php echo T($status); ?></span>
                    </div>

                    <div class="client-actions">
                        <a href="client_registration.php?id=<?php echo $client['id']; ?>" class="btn-action" title="Update" data-translate-title="tooltip_update">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </a>
                        <button type="button" class="btn-action whatsapp-trigger-btn" title="WhatsApp" data-translate-title="tooltip_whatsapp" data-whatsapp-target="#<?php echo whatsapp_modal_id($client['id']); ?>">
                            <i class="fa-brands fa-whatsapp"></i>
                        </button>
                    </div>
                </div>

                <?php echo render_whatsapp_modal($client); ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/../src/templates/footer.php';
?>