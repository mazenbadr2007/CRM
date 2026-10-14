<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../access_helper.php';
if (current_user_role() !== ROLE_COMPANY_ADMIN) {
    header("Location: " . BASE_URL . "/home.php");
    exit();
}
?>