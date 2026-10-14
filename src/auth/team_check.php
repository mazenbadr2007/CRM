<?php
/**
 * Page guard for the Sales Manager workspace ("My Team", team_dashboard.php).
 *
 *   sales_manager  -> allowed (data is scoped to their own team by access_helper.php)
 *   company_admin  -> sent to manager_dashboard.php (their company-wide page)
 *   sales_employee -> sent home
 *
 * Usage (top of a page):  require_once __DIR__ . '/../src/auth/team_check.php';
 */
require_once __DIR__ . '/auth_check.php';          // login + subscription + role hydration
require_once __DIR__ . '/../manager_helper.php';   // team functions used by the page

$__role = current_user_role();
if ($__role === ROLE_COMPANY_ADMIN) {
    header('Location: ' . BASE_URL . '/manager_dashboard.php');
    exit();
}
if ($__role !== ROLE_SALES_MANAGER) {
    header('Location: ' . BASE_URL . '/home.php');
    exit();
}
