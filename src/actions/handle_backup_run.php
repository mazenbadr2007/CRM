<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../super_admin_helper.php';
require_once __DIR__ . '/../backup_helper.php';
require_super_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/backups.php');
    exit();
}

// Both the standalone Backups page and the Backup Management section on
// the Super Admin dashboard post here; a hidden "redirect" field tells
// us which page to send the admin back to. Defaults to backups.php for
// backward compatibility.
$redirect_target = ($_POST['redirect'] ?? 'backups') === 'dashboard' ? 'super_admin_dashboard.php' : 'backups.php';

$backup = create_database_backup($conn, 'manual', $_SESSION['super_admin_username'] ?? 'super_admin');

if ($backup['status'] === 'success') {
    header('Location: ' . BASE_URL . '/' . $redirect_target . '?backup=success');
} else {
    header('Location: ' . BASE_URL . '/' . $redirect_target . '?backup=error');
}
exit();
