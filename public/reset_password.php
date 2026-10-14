<?php
session_start();
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/password_reset_helper.php';

$raw_token = isset($_GET['token']) ? $_GET['token'] : '';
$reset_user = $raw_token !== '' ? verify_password_reset_token($conn, $raw_token) : null;
$token_valid = $reset_user !== null;
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Elite Real Estate CRM</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/rtl.css">
    <script>
        (function() {
            const lang = localStorage.getItem('language') || 'en';
            const htmlEl = document.documentElement;
            htmlEl.lang = lang;
            htmlEl.dir = (lang === 'ar') ? 'rtl' : 'ltr';
        })();
    </script>
</head>
<body>

    <div class="auth-lang-switcher">
        <button type="button" class="btn btn-sm btn-outline-primary" data-lang="en">EN</button>
        <button type="button" class="btn btn-sm btn-outline-primary" data-lang="ar">AR</button>
    </div>

    <div class="container-fluid p-0">
        <div class="row g-0">
            <div class="col-lg-5 d-flex flex-column justify-content-center align-items-center bg-white form-column-wrapper">
                <div class="mobile-header d-lg-none">
                    <div class="slideshow-container"></div>
                </div>
                <div class="form-container">
                    <div class="text-center mb-5">
                        <h1 class="fw-bold" data-translate-key="reset_password_title">Reset Password</h1>
                        <?php if ($token_valid): ?>
                            <p class="text-muted" data-translate-key="reset_password_subtitle">Choose a new password for your account.</p>
                        <?php endif; ?>

                        <?php if (!$token_valid): ?>
                            <div class="alert alert-danger" data-translate-key="reset_token_invalid_msg">
                                This password reset link is invalid or has expired. Please request a new one.
                            </div>
                        <?php elseif (isset($_GET['error'])): ?>
                            <?php
                                $error_key = 'error_generic_fields';
                                $error_text = 'Please fill all fields correctly.';
                                if ($_GET['error'] === 'password_mismatch') {
                                    $error_key = 'error_password_mismatch';
                                    $error_text = 'Passwords do not match.';
                                } elseif ($_GET['error'] === 'password_too_short') {
                                    $error_key = 'error_password_too_short';
                                    $error_text = 'Password must be at least 8 characters long.';
                                } elseif ($_GET['error'] === 'invalid_token') {
                                    $error_key = 'reset_token_invalid_msg';
                                    $error_text = 'This password reset link is invalid or has expired. Please request a new one.';
                                }
                            ?>
                            <div class="alert alert-danger" data-translate-key="<?php echo $error_key; ?>"><?php echo $error_text; ?></div>
                        <?php endif; ?>
                    </div>

                    <?php if ($token_valid): ?>
                        <form action="../src/actions/handle_reset_password.php" method="POST">
                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($raw_token); ?>">
                            <div class="input-group mb-4">
                                <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                                <input type="password" name="password" class="form-control" data-translate-key="create_password_ph" placeholder="Create Password" required minlength="8">
                            </div>
                            <div class="input-group mb-4">
                                <span class="input-group-text"><i class="fa-solid fa-check-double"></i></span>
                                <input type="password" name="confirm_password" class="form-control" data-translate-key="confirm_password_ph" placeholder="Confirm Password" required minlength="8">
                            </div>
                            <div class="d-grid mb-4">
                                <button type="submit" class="btn btn-primary fw-bold" data-translate-key="reset_password_btn">RESET PASSWORD</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="d-grid mb-4">
                            <a href="forgot_password.php" class="btn btn-primary fw-bold" data-translate-key="request_new_link_btn">REQUEST NEW LINK</a>
                        </div>
                    <?php endif; ?>

                    <div class="text-center">
                        <p class="mb-0"><a href="login.php" data-translate-key="back_to_login_link">Back to Login</a></p>
                    </div>
                </div>
            </div>
            <div class="col-lg-7 d-none d-lg-block image-column">
                <div class="slideshow-container"></div>
            </div>
        </div>
    </div>

    <script src="js/translations.js"></script>
    <script src="js/settings_manager.js"></script>
    <script src="js/script.js"></script>
</body>
</html>
