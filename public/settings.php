<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth/auth_check.php';
require_once __DIR__ . '/../src/company_settings_helper.php';
require_once __DIR__ . '/../src/api/api_auth_helper.php';
require_once __DIR__ . '/../src/storage_helper.php';

// Storage quota snapshot for this company (used / available / limit).
$storage_summary = get_company_storage_summary($conn, $_SESSION['company_id']);

// Always the session's own company — a company can only ever view/edit
// its own branding settings, never another company's.
$company_settings = get_company_settings($conn, $_SESSION['company_id']);
$company_logo_preview = company_logo_url($company_settings['logo_path']);

$company_settings_status = $_GET['company_settings'] ?? null;
$company_settings_error_reasons = isset($_GET['reason']) ? explode(',', $_GET['reason']) : [];

// API keys belonging to this company only.
$api_keys = list_api_keys($conn, $_SESSION['company_id']);
$new_api_key_plaintext = $_SESSION['_new_api_key_plaintext'] ?? null;
unset($_SESSION['_new_api_key_plaintext']);

require_once __DIR__ . '/../src/templates/header.php';
?>


<link rel="stylesheet" href="css/settings.css">


<div class="container-fluid settings-container">
    <div class="page-header">
        <h1 class="page-title" data-translate-key="settings_title">Settings</h1>
        <p class="page-subtitle" data-translate-key="settings_subtitle">Manage your application preferences.</p>
    </div>

    <?php if ($company_settings_status === 'success'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <span data-translate-key="company_settings_saved">Company settings saved successfully.</span>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif ($company_settings_status === 'error'): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <span data-translate-key="company_settings_error">Couldn't save company settings. Please check the fields below.</span>
            <?php if (!empty($company_settings_error_reasons)): ?>
                <ul class="mb-0 mt-1">
                    <?php foreach ($company_settings_error_reasons as $reason): ?>
                        <li><?php echo htmlspecialchars($reason); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <h6 class="card-title mb-0" data-translate-key="company_settings_title">Company Settings</h6>
            <p class="mb-0 mt-1" style="font-size:0.875rem;color:var(--bs-secondary-color);" data-translate-key="company_settings_subtitle">Customize your company's branding. These changes only affect your own company.</p>
        </div>
        <div class="card-body">
            <form action="../src/actions/handle_company_settings_save.php" method="POST" enctype="multipart/form-data" class="company-settings-form">

                <div class="company-settings-row">
                    <div class="company-logo-field">
                        <label class="form-label" data-translate-key="company_logo_label">Logo</label>
                        <div class="company-logo-preview" id="companyLogoPreview">
                            <?php if ($company_logo_preview): ?>
                                <img src="<?php echo htmlspecialchars($company_logo_preview); ?>" alt="Company logo" id="companyLogoPreviewImg">
                            <?php else: ?>
                                <i class="fa-solid fa-building" id="companyLogoPlaceholderIcon"></i>
                                <img src="" alt="Company logo" id="companyLogoPreviewImg" style="display:none;">
                            <?php endif; ?>
                        </div>
                        <input type="file" class="form-control" id="companyLogoInput" name="logo" accept=".jpg,.jpeg,.png,.gif,.webp,.svg">
                        <small class="text-muted" data-translate-key="company_logo_hint">PNG, JPG, GIF, WEBP or SVG. Max 4MB.</small>
                    </div>

                    <div class="company-fields-col">
                        <div class="mb-3">
                            <label for="companyDisplayName" class="form-label" data-translate-key="company_name_label">Company Name</label>
                            <input type="text" class="form-control" id="companyDisplayName" name="display_name" maxlength="150"
                                   value="<?php echo htmlspecialchars($company_settings['display_name'] ?? ''); ?>"
                                   placeholder="My CRM">
                        </div>

                        <div class="mb-3">
                            <label for="companyThemeColorPicker" class="form-label" data-translate-key="company_theme_color_label">Theme Color</label>
                            <div class="theme-color-input-group">
                                <input type="color" class="form-control form-control-color" id="companyThemeColorPicker"
                                       value="<?php echo htmlspecialchars($company_settings['theme_color'] ?? COMPANY_DEFAULT_THEME_COLOR); ?>">
                                <input type="text" class="form-control" id="companyThemeColorHex" name="theme_color" maxlength="7"
                                       value="<?php echo htmlspecialchars($company_settings['theme_color'] ?? COMPANY_DEFAULT_THEME_COLOR); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                <h6 class="mb-3" data-translate-key="company_contact_info_title">Contact Information</h6>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="companyContactEmail" class="form-label" data-translate-key="company_contact_email_label">Email</label>
                        <input type="email" class="form-control" id="companyContactEmail" name="contact_email" maxlength="150"
                               value="<?php echo htmlspecialchars($company_settings['contact_email'] ?? ''); ?>" placeholder="info@company.com">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="companyContactPhone" class="form-label" data-translate-key="company_contact_phone_label">Phone</label>
                        <input type="text" class="form-control" id="companyContactPhone" name="contact_phone" maxlength="50"
                               value="<?php echo htmlspecialchars($company_settings['contact_phone'] ?? ''); ?>" placeholder="+20 100 000 0000">
                    </div>
                    <div class="col-12 mb-3">
                        <label for="companyContactAddress" class="form-label" data-translate-key="company_contact_address_label">Address</label>
                        <input type="text" class="form-control" id="companyContactAddress" name="contact_address" maxlength="255"
                               value="<?php echo htmlspecialchars($company_settings['contact_address'] ?? ''); ?>" placeholder="123 Business St, Cairo, Egypt">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" data-translate-key="save_changes">Save Changes</button>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h6 class="card-title mb-0" data-translate-key="backup_database_title">Database Backup</h6>
            <p class="mb-0 mt-1" style="font-size:0.875rem;color:var(--bs-secondary-color);" data-translate-key="backup_database_subtitle">Download a full backup of your company's data — clients, properties, developers, users, and settings.</p>
        </div>
        <div class="card-body">
            <div class="setting-item" style="border:0;padding:0;">
                <div class="info">
                    <h6 data-translate-key="backup_database_label">Backup Database</h6>
                    <p data-translate-key="backup_database_desc">Generates a secure backup file containing only your company's data and downloads it to your computer.</p>
                </div>
                <div class="action">
                    <a href="../src/actions/handle_settings_backup_download.php" class="btn btn-primary">
                        <i class="fa-solid fa-database me-2"></i><span data-translate-key="backup_database_button">Backup Database</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h6 class="card-title mb-0" data-translate-key="storage_usage_title">Storage</h6>
            <p class="mb-0 mt-1" style="font-size:0.875rem;color:var(--bs-secondary-color);" data-translate-key="storage_usage_subtitle">Your company's document storage quota, set by your Super Admin.</p>
        </div>
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                <div>
                    <small class="text-muted d-block" data-translate-key="storage_used_label">Used Storage</small>
                    <strong><?php echo format_storage_bytes($storage_summary['used_bytes']); ?></strong>
                </div>
                <div class="text-end">
                    <small class="text-muted d-block" data-translate-key="storage_available_label">Available Storage</small>
                    <strong><?php echo format_storage_bytes($storage_summary['available_bytes']); ?></strong>
                </div>
            </div>
            <div class="progress" style="height: 10px;">
                <div class="progress-bar <?php echo $storage_summary['limit_reached'] ? 'bg-danger' : ($storage_summary['percent_used'] >= 85 ? 'bg-warning' : 'bg-primary'); ?>"
                     role="progressbar"
                     style="width: <?php echo $storage_summary['percent_used']; ?>%;"
                     aria-valuenow="<?php echo $storage_summary['percent_used']; ?>" aria-valuemin="0" aria-valuemax="100">
                </div>
            </div>
            <small class="text-muted d-block mt-2">
                <?php echo $storage_summary['percent_used']; ?>% <span data-translate-key="storage_used_of_quota">used of</span> <?php echo format_storage_bytes($storage_summary['limit_bytes']); ?> <span data-translate-key="storage_quota_label">quota</span>
            </small>
            <?php if ($storage_summary['limit_reached']): ?>
                <div class="alert alert-danger mt-3 mb-0 py-2 px-3" role="alert">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i>
                    <span data-translate-key="storage_limit_reached_notice"><?php echo htmlspecialchars(storage_limit_reached_message()); ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h6 class="card-title mb-0" data-translate-key="api_keys_title">API Access</h6>
            <p class="mb-0 mt-1" style="font-size:0.875rem;color:var(--bs-secondary-color);" data-translate-key="api_keys_subtitle">Let external systems read/create your company's clients and read your properties over the REST API. Keys only ever grant access to your own company's data.</p>
        </div>
        <div class="card-body">
            <?php if ($new_api_key_plaintext): ?>
                <div class="alert alert-warning" role="alert">
                    <strong data-translate-key="api_key_created_title">New API key created.</strong>
                    <span data-translate-key="api_key_created_desc">Copy it now — for your security, it will not be shown again.</span>
                    <div class="input-group mt-2">
                        <input type="text" class="form-control" readonly value="<?php echo htmlspecialchars($new_api_key_plaintext); ?>" id="newApiKeyValue">
                        <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('newApiKeyValue').value)" data-translate-key="copy">Copy</button>
                    </div>
                </div>
            <?php endif; ?>

            <form action="../src/actions/handle_api_key_create.php" method="POST" class="d-flex gap-2 mb-3">
                <input type="text" class="form-control" name="label" maxlength="150" placeholder="Label (e.g. Website Integration)" data-translate-key-placeholder="api_key_label_placeholder">
                <button type="submit" class="btn btn-primary text-nowrap">
                    <i class="fa-solid fa-key me-1"></i><span data-translate-key="generate_api_key">Generate New Key</span>
                </button>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th data-translate-key="label">Label</th>
                            <th data-translate-key="key">Key</th>
                            <th data-translate-key="status">Status</th>
                            <th data-translate-key="last_used">Last Used</th>
                            <th class="text-end" data-translate-key="actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($api_keys)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-3" data-translate-key="no_api_keys">No API keys yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($api_keys as $k): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($k['label'] ?? '—'); ?></td>
                                <td><code><?php echo htmlspecialchars($k['key_prefix']); ?>…</code></td>
                                <td>
                                    <?php if ($k['status'] === 'active'): ?>
                                        <span class="badge bg-success" data-translate-key="status_active">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary" data-translate-key="status_revoked">Revoked</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $k['last_used_at'] ? htmlspecialchars($k['last_used_at']) : ''; ?><?php if (!$k['last_used_at']): ?><span data-translate-key="never_used">Never</span><?php endif; ?></td>
                                <td class="text-end">
                                    <?php if ($k['status'] === 'active'): ?>
                                        <form action="../src/actions/handle_api_key_revoke.php" method="POST" class="d-inline"
                                              onsubmit="return confirm(window.tr ? window.tr('revoke_key_confirm') : 'Revoke this API key? Anything using it will stop working immediately.');">
                                            <input type="hidden" name="id" value="<?php echo (int) $k['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Revoke" data-translate-title="revoke_key">
                                                <i class="fa-solid fa-ban"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            
            <div class="setting-item">
                <div class="info">
                    <h6 data-translate-key="display_settings_title">Display Settings</h6>
                    <p data-translate-key="dark_mode_desc">Switch to a darker theme to reduce eye strain, especially at night.</p>
                </div>
                <div class="action">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="darkModeToggle">
                    </div>
                </div>
            </div>

         
            <div class="setting-item">
                <div class="info">
                    <h6 data-translate-key="language_settings_title">Language Settings</h6>
                    <p data-translate-key="language_desc">Choose your preferred language for the application interface.</p>
                </div>
                <div class="action">
                    <div class="language-buttons btn-group">
                        <button class="btn btn-outline-primary" data-lang="en">
                            <i class="fa-solid fa-flag-usa me-2"></i> English
                        </button>
                        <button class="btn btn-outline-primary" data-lang="ar">
                            <i class="fa-solid fa-flag me-2"></i> العربية
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const colorPicker = document.getElementById('companyThemeColorPicker');
    const colorHex = document.getElementById('companyThemeColorHex');
    const logoInput = document.getElementById('companyLogoInput');
    const logoPreviewImg = document.getElementById('companyLogoPreviewImg');
    const logoPlaceholderIcon = document.getElementById('companyLogoPlaceholderIcon');
    const hexPattern = /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/;

    if (colorPicker && colorHex) {
        colorPicker.addEventListener('input', function () {
            colorHex.value = this.value;
        });
        colorHex.addEventListener('input', function () {
            if (hexPattern.test(this.value)) {
                colorPicker.value = this.value;
            }
        });
    }

    if (logoInput && logoPreviewImg) {
        logoInput.addEventListener('change', function () {
            const file = this.files && this.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = function (e) {
                logoPreviewImg.src = e.target.result;
                logoPreviewImg.style.display = 'block';
                if (logoPlaceholderIcon) logoPlaceholderIcon.style.display = 'none';
            };
            reader.readAsDataURL(file);
        });
    }
});
</script>

<?php

require_once __DIR__ . '/../src/templates/footer.php';
?>