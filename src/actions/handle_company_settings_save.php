<?php
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../access_helper.php';
require_company_admin();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../company_settings_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/settings.php');
    exit();
}

// Always the current, session-derived company. Never trust a company_id
// from the request itself — this is what guarantees a company can only
// ever change its own settings.
$company_id = $_SESSION['company_id'] ?? null;

if (!$company_id) {
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}

$fields = [];
$errors = [];

// --- Name ---
if (isset($_POST['display_name'])) {
    $display_name = trim($_POST['display_name']);
    if (mb_strlen($display_name) > 150) {
        $errors[] = 'name_too_long';
    } else {
        // Empty string clears back to the default ("My CRM").
        $fields['display_name'] = $display_name === '' ? null : $display_name;
    }
}

// --- Theme color ---
if (isset($_POST['theme_color'])) {
    $theme_color = trim($_POST['theme_color']);
    if ($theme_color === '') {
        $fields['theme_color'] = COMPANY_DEFAULT_THEME_COLOR;
    } elseif (is_valid_hex_color($theme_color)) {
        $fields['theme_color'] = $theme_color;
    } else {
        $errors[] = 'invalid_theme_color';
    }
}

// --- Contact information ---
if (isset($_POST['contact_email'])) {
    $contact_email = trim($_POST['contact_email']);
    if ($contact_email !== '' && !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'invalid_contact_email';
    } else {
        $fields['contact_email'] = $contact_email === '' ? null : $contact_email;
    }
}

if (isset($_POST['contact_phone'])) {
    $contact_phone = trim($_POST['contact_phone']);
    if (mb_strlen($contact_phone) > 50) {
        $errors[] = 'contact_phone_too_long';
    } else {
        $fields['contact_phone'] = $contact_phone === '' ? null : $contact_phone;
    }
}

if (isset($_POST['contact_address'])) {
    $contact_address = trim($_POST['contact_address']);
    if (mb_strlen($contact_address) > 255) {
        $errors[] = 'contact_address_too_long';
    } else {
        $fields['contact_address'] = $contact_address === '' ? null : $contact_address;
    }
}

// --- Logo upload (optional) ---
if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['logo'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'logo_upload_failed';
    } elseif ($file['size'] <= 0 || $file['size'] > COMPANY_LOGO_MAX_SIZE) {
        $errors[] = 'logo_too_large';
    } elseif (!is_allowed_logo_extension($file['name'])) {
        $errors[] = 'logo_invalid_type';
    } elseif (!is_uploaded_file($file['tmp_name'])) {
        $errors[] = 'logo_upload_failed';
    } else {
        $old_settings = get_company_settings($conn, $company_id);

        $stored_name = generate_company_logo_filename($company_id, $file['name']);
        $target_dir = company_logo_storage_dir($company_id);
        $target_path = $target_dir . '/' . $stored_name;

        if (!move_uploaded_file($file['tmp_name'], $target_path)) {
            $errors[] = 'logo_upload_failed';
        } else {
            $fields['logo_path'] = $stored_name;

            // Clean up the previous logo file, if any, now that the new
            // one is safely in place.
            if (!empty($old_settings['logo_path'])) {
                $old_path = $target_dir . '/' . basename($old_settings['logo_path']);
                if (is_file($old_path)) {
                    @unlink($old_path);
                }
            }
        }
    }
}

if (empty($errors) && !empty($fields)) {
    update_company_settings($conn, $company_id, $fields);
    header('Location: ' . BASE_URL . '/settings.php?company_settings=success');
    exit();
}

if (!empty($errors)) {
    header('Location: ' . BASE_URL . '/settings.php?company_settings=error&reason=' . urlencode(implode(',', $errors)));
    exit();
}

header('Location: ' . BASE_URL . '/settings.php');
exit();
