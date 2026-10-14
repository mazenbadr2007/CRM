<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Elite Real Estate CRM</title>

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
                        <h1 class="fw-bold" data-translate-key="forgot_password_title">Forgot Password</h1>
                        <p class="text-muted" data-translate-key="forgot_password_subtitle">Enter your company and email to receive a reset link.</p>
                        <?php if (isset($_GET['sent'])): ?>
                            <div class="alert alert-success" data-translate-key="forgot_password_sent_msg">
                                If an account matches those details, a password reset link has been sent to the registered email address.
                            </div>
                        <?php endif; ?>
                        <?php if (isset($_GET['error']) && $_GET['error'] === 'empty_fields'): ?>
                            <div class="alert alert-danger" data-translate-key="error_generic_fields">Please fill all fields correctly.</div>
                        <?php endif; ?>
                    </div>

                    <form action="../src/actions/handle_forgot_password.php" method="POST">
                        <div class="input-group mb-4">
                            <span class="input-group-text"><i class="fa-solid fa-building"></i></span>
                            <input type="text" name="company_name" class="form-control" data-translate-key="company_name_ph" placeholder="Company Name" required>
                        </div>
                        <div class="input-group mb-4">
                            <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                            <input type="email" name="email" class="form-control" data-translate-key="email_ph" placeholder="Email Address" required>
                        </div>
                        <div class="d-grid mb-4">
                            <button type="submit" class="btn btn-primary fw-bold" data-translate-key="send_reset_link_btn">SEND RESET LINK</button>
                        </div>
                        <div class="text-center">
                            <p class="mb-0"><a href="login.php" data-translate-key="back_to_login_link">Back to Login</a></p>
                        </div>
                    </form>
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
