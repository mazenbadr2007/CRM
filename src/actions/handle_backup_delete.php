<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../super_admin_helper.php';
require_once __DIR__ . '/../backup_helper.php';
require_super_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/backups.php');
    exit();
}

// See handle_backup_run.php for why this redirect target is dynamic.
$redirect_target = ($_POST['redirect'] ?? 'backups') === 'dashboard' ? 'super_admin_dashboard.php' : 'backups.php';

$id = $_POST['id'] ?? null;
if ($id) {
    delete_backup($conn, $id);
}

header('Location: ' . BASE_URL . '/' . $redirect_target . '?backup=deleted');
exit();
