<?php
require_once __DIR__ . '/../src/auth/auth_check.php';
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/developer_helper.php';

$company_id = $_SESSION['company_id'] ?? null;
$project_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$project = get_project($conn, $project_id, $company_id);

if (!$project) {
    header("Location: developers.php");
    exit();
}

$developer = get_developer($conn, $project['developer_id'], $company_id);
$properties = get_properties_by_project($conn, $project_id, $company_id);

require_once __DIR__ . '/../src/templates/header.php';
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/developers.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/property_inventory.css">
<div class="container-fluid py-4 px-4">
    <div class="mb-3">
        <a href="developer_view.php?id=<?php echo (int) $project['developer_id']; ?>" class="text-decoration-none">
            <i class="fa-solid fa-arrow-left me-1 rtl-flip"></i> <span data-translate-key="back_to_projects">Back to Projects</span>
        </a>
    </div>

    <div class="mb-4">
        <h2 class="fw-bold mb-0"><i class="fa-solid fa-building me-2"></i><?php echo htmlspecialchars($project['name']); ?></h2>
        <p class="text-muted mb-0">
            <span data-translate-key="label_project_developer">Developer:</span>
            <?php echo $developer ? htmlspecialchars($developer['name']) : '—'; ?>
            <?php if (!empty($project['location'])): ?>
                <span class="mx-2">•</span><i class="fa-solid fa-location-dot me-1"></i><?php echo htmlspecialchars($project['location']); ?>
            <?php endif; ?>
        </p>
    </div>

    <div class="card shadow-sm property-card">
        <div class="card-body">
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th data-translate-key="col_unit_type">Unit Type</th>
                            <th data-translate-key="col_area">Area</th>
                            <th data-translate-key="col_bedrooms">Bedrooms</th>
                            <th data-translate-key="col_price">Price</th>
                            <th data-translate-key="col_down_payment">Down Payment</th>
                            <th data-translate-key="col_installments">Installments</th>
                            <th data-translate-key="col_status">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($properties)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4" data-translate-key="no_properties_yet">No properties yet.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($properties as $p): ?>
                            <?php
                                $status = strtolower($p['status']);
                                $badge_class = 'bg-success';
                                $status_key = 'status_available';
                                if ($status === 'sold') {
                                    $badge_class = 'bg-danger';
                                    $status_key = 'status_sold';
                                } elseif ($status === 'reserved') {
                                    $badge_class = 'bg-warning text-dark';
                                    $status_key = 'status_reserved';
                                }
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($p['unit_type']); ?></td>
                                <td><?php echo $p['area'] !== null ? htmlspecialchars($p['area']) . ' m²' : '—'; ?></td>
                                <td><?php echo $p['bedrooms'] !== null ? (int) $p['bedrooms'] : '—'; ?></td>
                                <td><?php echo $p['price'] !== null ? number_format((float) $p['price'], 2) : '—'; ?></td>
                                <td><?php echo $p['down_payment'] !== null ? number_format((float) $p['down_payment'], 2) : '—'; ?></td>
                                <td><?php echo htmlspecialchars($p['installments'] ?: '—'); ?></td>
                                <td><span class="badge <?php echo $badge_class; ?>" data-translate-key="<?php echo $status_key; ?>"><?php echo htmlspecialchars(ucfirst($p['status'])); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile card view -->
            <div class="property-cards d-md-none">
                <?php if (empty($properties)): ?>
                    <p class="text-center text-muted py-4 mb-0" data-translate-key="no_properties_yet">No properties yet.</p>
                <?php endif; ?>
                <?php foreach ($properties as $p): ?>
                    <?php
                        $status = strtolower($p['status']);
                        $badge_class = 'bg-success';
                        $status_key = 'status_available';
                        if ($status === 'sold') {
                            $badge_class = 'bg-danger';
                            $status_key = 'status_sold';
                        } elseif ($status === 'reserved') {
                            $badge_class = 'bg-warning text-dark';
                            $status_key = 'status_reserved';
                        }
                    ?>
                    <div class="property-mobile-card">
                        <div class="property-mobile-card-header">
                            <span class="property-mobile-project"><?php echo htmlspecialchars($p['unit_type']); ?></span>
                            <span class="badge <?php echo $badge_class; ?>" data-translate-key="<?php echo $status_key; ?>"><?php echo htmlspecialchars(ucfirst($p['status'])); ?></span>
                        </div>
                        <div class="property-mobile-row"><span class="property-mobile-label" data-translate-key="label_area">Area:</span> <span><?php echo $p['area'] !== null ? htmlspecialchars($p['area']) . ' m²' : '—'; ?></span></div>
                        <div class="property-mobile-row"><span class="property-mobile-label" data-translate-key="label_bedrooms">Bedrooms:</span> <span><?php echo $p['bedrooms'] !== null ? (int) $p['bedrooms'] : '—'; ?></span></div>
                        <div class="property-mobile-row"><span class="property-mobile-label" data-translate-key="label_price">Price:</span> <span><?php echo $p['price'] !== null ? number_format((float) $p['price'], 2) : '—'; ?></span></div>
                        <div class="property-mobile-row"><span class="property-mobile-label" data-translate-key="label_down_payment">Down Payment:</span> <span><?php echo $p['down_payment'] !== null ? number_format((float) $p['down_payment'], 2) : '—'; ?></span></div>
                        <div class="property-mobile-row"><span class="property-mobile-label" data-translate-key="label_installments">Installments:</span> <span><?php echo htmlspecialchars($p['installments'] ?: '—'); ?></span></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../src/templates/footer.php'; ?>
