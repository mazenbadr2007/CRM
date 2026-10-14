<?php
$current_page = basename($_SERVER['PHP_SELF']);

// Reminder notification badge count (sidebar). Computed defensively so the
// header still renders fine on pages without a DB connection/session yet
// (e.g. login/register).
$reminder_due_count = 0;
$notification_unread_count = 0;
$show_notification_center = false;
if (isset($conn) && isset($_SESSION['user_id']) && $current_page !== 'login.php' && $current_page !== 'register.php') {
    require_once __DIR__ . '/../reminder_helper.php';
    $reminder_counts = get_reminder_counts($conn, $_SESSION['user_id']);
    $reminder_due_count = $reminder_counts['total_due'];

    require_once __DIR__ . '/../notification_helper.php';
    generate_followup_notifications($conn, $_SESSION['user_id']);
    $notification_unread_count = get_unread_notification_count($conn, $_SESSION['user_id']);
    $show_notification_center = true;
}

// Role-based sidebar links (backend permissions are enforced separately):
//   company_admin  -> Manager Dashboard + Users Management
//   sales_manager  -> My Team (own team page, different from the admin dashboard)
//   sales_employee -> neither
$nav_show_manager_dashboard = false;
$nav_show_my_team = false;
$nav_show_users_management = false;
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../access_helper.php';
    $nav_role = current_user_role();
    $nav_show_manager_dashboard = ($nav_role === ROLE_COMPANY_ADMIN);
    $nav_show_my_team = ($nav_role === ROLE_SALES_MANAGER);
    $nav_show_users_management = ($nav_role === ROLE_COMPANY_ADMIN);
}

// Company branding (logo / display name / theme color). Scoped entirely
// to the logged-in user's own company_id from the session, so one
// company's branding can never leak onto another company's pages.
$company_display_name = 'My CRM';
$company_logo_src = null;
$company_theme_color = null;
if (isset($conn) && isset($_SESSION['company_id']) && $current_page !== 'login.php' && $current_page !== 'register.php') {
    require_once __DIR__ . '/../company_settings_helper.php';
    $company_branding = get_company_settings($conn, $_SESSION['company_id']);
    if (!empty($company_branding['display_name'])) {
        $company_display_name = $company_branding['display_name'];
    }
    if (!empty($company_branding['logo_path'])) {
        $company_logo_src = company_logo_url($company_branding['logo_path']);
    }
    if (!empty($company_branding['theme_color']) && is_valid_hex_color($company_branding['theme_color'])) {
        $company_theme_color = $company_branding['theme_color'];
    }
}
?>
<!DOCTYPE html>

<html> 
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elite Real Estate CRM</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/dark_mode.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/notifications.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/rtl.css">
    <?php if ($company_theme_color): ?>
    <style>
        /* Company theme color override — derived from this company's own
           settings row only, so it can never affect another company. */
        :root {
            --active-bg: <?php echo htmlspecialchars($company_theme_color); ?>;
            --bs-primary: <?php echo htmlspecialchars($company_theme_color); ?>;
        }
    </style>
    <?php endif; ?>
    <script>
        (function() {
            // Apply the stored theme before first paint, and briefly suppress
            // transitions so the initial theme doesn't visibly animate in.
            const theme = localStorage.getItem('theme');
            const lang = localStorage.getItem('language') || 'en';
            const htmlEl = document.documentElement;
            htmlEl.classList.add('theme-no-transition');
            if (theme === 'dark') {
                htmlEl.classList.add('dark-mode');
            }
            htmlEl.lang = lang;
            htmlEl.dir = (lang === 'ar') ? 'rtl' : 'ltr';
            window.addEventListener('DOMContentLoaded', function() {
                requestAnimationFrame(function() {
                    htmlEl.classList.remove('theme-no-transition');
                    htmlEl.classList.add('theme-ready');
                });
            });
        })();
    </script>
</head>
<body class="<?php echo $current_page === 'login.php' || $current_page === 'register.php' ? 'auth-page' : ''; ?>">
    <div class="page-wrapper">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <?php if ($company_logo_src): ?>
                    <img src="<?php echo htmlspecialchars($company_logo_src); ?>" alt="<?php echo htmlspecialchars($company_display_name); ?>" class="sidebar-logo">
                <?php endif; ?>
                <h3<?php echo $company_display_name === 'My CRM' ? ' data-translate-key="my_crm"' : ''; ?>><?php echo htmlspecialchars($company_display_name); ?></h3>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li class="<?php echo ($current_page === 'home.php') ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_URL; ?>/home.php"><i class="fa-solid fa-house-chimney fa-fw"></i> <span data-translate-key="home">Home</span></a>
                    </li>
                    <li class="<?php echo ($current_page === 'client_registration.php') ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_URL; ?>/client_registration.php"><i class="fa-solid fa-address-card fa-fw"></i> <span data-translate-key="client_registration">Client Registration</span></a>
                    </li>
                     <li class="<?php echo ($current_page === 'clients_dashboard.php') ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_URL; ?>/clients_dashboard.php"><i class="fa-solid fa-gauge-high fa-fw"></i> <span data-translate-key="client_dashboard">Client Dashboard</span></a>
                    </li>
                    <li class="<?php echo ($current_page === 'sales_dashboard.php') ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_URL; ?>/sales_dashboard.php"><i class="fa-solid fa-chart-line fa-fw"></i> <span data-translate-key="sales_dashboard">Sales Dashboard</span></a>
                    </li>
                     <li class="<?php echo ($current_page === 'sales_pipeline.php') ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_URL; ?>/sales_pipeline.php"><i class="fa-solid fa-table-columns fa-fw"></i> <span data-translate-key="sales_pipeline">Sales Pipeline</span></a>
                    </li>
                    <li class="<?php echo ($current_page === 'developers.php' || $current_page === 'developer_view.php' || $current_page === 'project_properties.php') ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_URL; ?>/developers.php"><i class="fa-solid fa-city fa-fw"></i> <span data-translate-key="developers">Developers</span></a>
                    </li>
                    <li class="<?php echo ($current_page === 'property_inventory.php') ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_URL; ?>/property_inventory.php"><i class="fa-solid fa-building fa-fw"></i> <span data-translate-key="property_inventory">Property Inventory</span></a>
                    </li>
                    <li class="<?php echo ($current_page === 'property_matching.php') ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_URL; ?>/property_matching.php"><i class="fa-solid fa-bullseye fa-fw"></i> <span data-translate-key="property_matching">Property Matching</span></a>
                    </li>
                    <li class="<?php echo ($current_page === 'commission_calculator.php') ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_URL; ?>/commission_calculator.php"><i class="fa-solid fa-calculator fa-fw"></i> <span data-translate-key="commission_calculator">Commission Calculator</span></a>
                    </li>
                    <li class="<?php echo ($current_page === 'installment_tracker.php') ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_URL; ?>/installment_tracker.php"><i class="fa-solid fa-money-check-dollar fa-fw"></i> <span data-translate-key="installment_tracker">Installment Tracker</span></a>
                    </li>
                    <?php if ($nav_show_manager_dashboard): ?>
                    <li class="<?php echo ($current_page === 'manager_dashboard.php') ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_URL; ?>/manager_dashboard.php"><i class="fa-solid fa-user-tie fa-fw"></i> <span data-translate-key="manager_dashboard">Manager Dashboard</span></a>
                    </li>
                    <?php endif; ?>
                    <?php if ($nav_show_my_team): ?>
                    <li class="<?php echo ($current_page === 'team_dashboard.php') ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_URL; ?>/team_dashboard.php"><i class="fa-solid fa-people-group fa-fw"></i> <span data-translate-key="nav_my_team">My Team</span></a>
                    </li>
                    <?php endif; ?>
                    <?php if ($nav_show_users_management): ?>
                    <li class="<?php echo ($current_page === 'users_management.php') ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_URL; ?>/users_management.php"><i class="fa-solid fa-users fa-fw"></i> <span data-translate-key="users_management">Users Management</span></a>
                    </li>
                    <?php endif; ?>
                     <li class="<?php echo ($current_page === 'follow_ups.php') ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_URL; ?>/follow_ups.php"><i class="fa-solid fa-headset fa-fw"></i> <span data-translate-key="follow_ups">Follow-ups</span></a>
                    </li>
                     <li class="<?php echo ($current_page === 'reminders.php') ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_URL; ?>/reminders.php">
                            <i class="fa-solid fa-bell fa-fw"></i> <span data-translate-key="reminders">Reminders</span>
                            <?php if ($reminder_due_count > 0): ?>
                                <span class="nav-badge"><?php echo $reminder_due_count > 99 ? '99+' : $reminder_due_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="<?php echo ($current_page === 'charts.php') ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_URL; ?>/charts.php"><i class="fa-solid fa-chart-pie fa-fw"></i> <span data-translate-key="charts">Charts</span></a>
                    </li>
                    <li class="<?php echo ($current_page === 'calendar.php') ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_URL; ?>/calendar.php"><i class="fa-solid fa-calendar-days fa-fw"></i> <span data-translate-key="calendar">Calendar</span></a>
                    </li>
                </ul>
            </nav>
            <div class="sidebar-footer">
                <ul>
                    <li class="<?php echo ($current_page === 'settings.php') ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_URL; ?>/settings.php"><i class="fa-solid fa-gear fa-fw"></i> <span data-translate-key="settings">Settings</span></a>
                    </li>
                    <li>
                        <a href="<?php echo BASE_URL; ?>/../src/actions/handle_logout.php"><i class="fa-solid fa-arrow-right-from-bracket fa-fw"></i> <span data-translate-key="logout">Logout</span></a>
                    </li>
                </ul>
            </div>
        </aside>
        <main class="main-content">
            <?php if ($show_notification_center): ?>
            <div class="notification-center" id="notificationCenter">
                <button type="button" class="notification-bell-btn" id="notificationBellBtn" aria-haspopup="true" aria-expanded="false">
                    <i class="fa-solid fa-bell"></i>
                    <span class="notification-badge" id="notificationBadge" style="<?php echo $notification_unread_count > 0 ? '' : 'display:none;'; ?>">
                        <?php echo $notification_unread_count > 99 ? '99+' : $notification_unread_count; ?>
                    </span>
                </button>
                <div class="notification-panel" id="notificationPanel">
                    <div class="notification-panel-header">
                        <span data-translate-key="notifications">Notifications</span>
                        <button type="button" class="notification-mark-all-btn" id="notificationMarkAllBtn" data-translate-key="mark_all_read">Mark all as read</button>
                    </div>
                    <div class="notification-list" id="notificationList">
                        <div class="notification-empty" data-translate-key="loading_notifications">Loading...</div>
                    </div>
                </div>
            </div>
            <div class="smart-alert-stack" id="smartAlertStack" aria-live="polite"></div>
            <?php endif; ?>
            <nav class="navbar navbar-expand-lg d-lg-none mobile-navbar">
                <div class="container-fluid">
                    <a class="navbar-brand" href="<?php echo BASE_URL; ?>/home.php"<?php echo $company_display_name === 'My CRM' ? ' data-translate-key="my_crm"' : ''; ?>>
                        <?php if ($company_logo_src): ?>
                            <img src="<?php echo htmlspecialchars($company_logo_src); ?>" alt="<?php echo htmlspecialchars($company_display_name); ?>" class="mobile-navbar-logo">
                        <?php else: ?>
                            <?php echo htmlspecialchars($company_display_name); ?>
                        <?php endif; ?>
                    </a>
                    <div class="mobile-navbar-bell-slot" id="mobileNavbarBellSlot"></div>
                    <div class="d-flex align-items-center gap-2">
                        <button class="btn menu-toggle-btn" type="button" id="mobile-menu-toggle">
                            <i class="fa-solid fa-bars"></i>
                        </button>
                    </div>
                </div>
            </nav>

            <?php if ($show_notification_center): ?>
            <script>
                // Move the single #notificationCenter bell (with all its
                // existing IDs/listeners intact) into the mobile navbar's
                // bell slot on small screens, and back to its original
                // fixed-position spot on large screens — so there is only
                // ever one bell button, never two overlapping ones.
                (function() {
                    var center = document.getElementById('notificationCenter');
                    var slot = document.getElementById('mobileNavbarBellSlot');
                    var mainContent = center ? center.parentElement : null;
                    if (!center || !slot || !mainContent) return;

                    function placeBell() {
                        if (window.innerWidth < 992) {
                            if (center.parentElement !== slot) {
                                slot.appendChild(center);
                            }
                        } else if (center.parentElement !== mainContent) {
                            mainContent.insertBefore(center, mainContent.firstChild);
                        }
                    }
                    placeBell();
                    window.addEventListener('resize', placeBell);
                })();
            </script>
            <?php endif; ?>
          