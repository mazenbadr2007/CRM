<?php
require_once __DIR__ . '/../src/auth/auth_check.php';
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/pipeline_helper.php';
require_once __DIR__ . '/../src/access_helper.php';

ensure_pipeline_stage_column($conn);

$user_id = $_SESSION['user_id'];
// Role-based scope (see access_helper.php):
//   company_admin  -> whole company (no user filter)
//   sales_manager  -> own leads + own team's leads
//   sales_employee -> own assigned leads only
// Admin and manager also see which employee owns each card.
$show_employee_name = access_can_see_team();
$company_id = $_SESSION['company_id'] ?? null;

$filter = $show_employee_name ? access_user_filter($conn, 'c.user_id') : access_user_filter($conn, 'user_id');
if ($show_employee_name) {
    $sql = "SELECT c.id, c.client_name, c.phone_number, c.project_name, c.project_location,
                   c.unit_price, c.pipeline_stage, c.final_status, u.username AS employee_name
            FROM clients c JOIN users u ON c.user_id = u.id
            WHERE c.company_id = ?" . $filter['sql'] . "
            ORDER BY c.created_at DESC";
} else {
    $sql = "SELECT id, client_name, phone_number, project_name, project_location,
                   unit_price, pipeline_stage, final_status
            FROM clients WHERE company_id = ?" . $filter['sql'] . " ORDER BY created_at DESC";
}
$stmt = $conn->prepare($sql);
$types = 'i' . $filter['types'];
$params = array_merge([$company_id], $filter['params']);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$clients = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Group clients into their pipeline stage bucket.
$board = [];
foreach (PIPELINE_STAGES as $stage) {
    $board[$stage] = [];
}
foreach ($clients as $client) {
    $stage = normalize_pipeline_stage($client['pipeline_stage'] ?? '');
    $board[$stage][] = $client;
}

$pipeline_stage_keys = [
    'New Lead'         => 'New_Lead',
    'Contacted'        => 'Contacted',
    'Interested'       => 'Interested',
    'Visit Scheduled'  => 'Pipeline_Visit_Scheduled',
    'Negotiation'      => 'Negotiation',
    'Won'              => 'Won',
    'Lost'             => 'Lost',
];

require_once __DIR__ . '/../src/templates/header.php';
?>
<link rel="stylesheet" href="css/sales_pipeline.css">

<div class="container-fluid">
    <div class="page-header">
        <h1 class="page-title" data-translate-key="sales_pipeline_title">Sales Pipeline</h1>
        <p class="page-subtitle" data-translate-key="sales_pipeline_subtitle">Drag a client card between stages to update their status automatically.</p>
    </div>

    <div class="kanban-board" id="kanbanBoard">
        <?php foreach (PIPELINE_STAGES as $stage): ?>
            <?php $stage_slug = str_replace(' ', '-', strtolower($stage)); ?>
            <div class="kanban-column" data-stage="<?php echo htmlspecialchars($stage); ?>">
                <div class="kanban-column-header stage-<?php echo $stage_slug; ?>">
                    <span class="kanban-column-title"><?php echo T($stage, $pipeline_stage_keys[$stage] ?? null); ?></span>
                    <span class="kanban-count" data-count-for="<?php echo htmlspecialchars($stage); ?>"><?php echo count($board[$stage]); ?></span>
                </div>
                <div class="kanban-column-body" data-stage-dropzone="<?php echo htmlspecialchars($stage); ?>">
                    <?php if (empty($board[$stage])): ?>
                        <div class="kanban-empty" data-translate-key="pipeline_empty_column">No clients here yet.</div>
                    <?php endif; ?>
                    <?php foreach ($board[$stage] as $client): ?>
                        <div class="kanban-card"
                             draggable="true"
                             data-client-id="<?php echo (int) $client['id']; ?>"
                             tabindex="0">
                            <div class="kanban-card-header">
                                <span class="kanban-card-name"><?php echo htmlspecialchars($client['client_name']); ?></span>
                            </div>
                            <div class="kanban-card-body">
                                <div class="kanban-card-row">
                                    <i class="fa-solid fa-phone"></i> <?php echo htmlspecialchars($client['phone_number']); ?>
                                </div>
                                <?php if (!empty($client['project_name']) && $client['project_name'] !== '0'): ?>
                                <div class="kanban-card-row">
                                    <i class="fa-solid fa-building"></i> <?php echo htmlspecialchars($client['project_name']); ?>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($client['project_location'])): ?>
                                <div class="kanban-card-row">
                                    <i class="fa-solid fa-location-dot"></i> <?php echo T($client['project_location']); ?>
                                </div>
                                <?php endif; ?>
                                <?php if ($show_employee_name && !empty($client['employee_name'])): ?>
                                <div class="kanban-card-row kanban-card-employee">
                                    <i class="fa-solid fa-user-tie"></i> <?php echo htmlspecialchars($client['employee_name']); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="kanban-card-footer">
                                <select class="form-select form-select-sm kanban-stage-select" data-client-id="<?php echo (int) $client['id']; ?>" aria-label="Move to stage">
                                    <?php foreach (PIPELINE_STAGES as $option): ?>
                                        <option value="<?php echo htmlspecialchars($option); ?>"
                                                data-translate-key="<?php echo htmlspecialchars($pipeline_stage_keys[$option] ?? ''); ?>"
                                                <?php echo ($option === $stage) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($option); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <a href="client_timeline.php?id=<?php echo (int) $client['id']; ?>" class="btn btn-sm btn-timeline kanban-card-timeline-btn" title="Timeline">
                                    <i class="fa-solid fa-timeline"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="kanban-toast" id="kanbanToast" role="status" aria-live="polite"></div>

<script src="js/sales_pipeline.js"></script>

<?php
require_once __DIR__ . '/../src/templates/footer.php';
?>
