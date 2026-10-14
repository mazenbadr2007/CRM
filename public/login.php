<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Elite Real Estate CRM</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/rtl.css">
    <script>
        (function() {
            // Apply the stored language before first paint to avoid a flash
            // of the wrong direction/text.
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
                        <h1 class="fw-bold" data-translate-key="login_welcome_title">Welcome Back</h1>
                        <p class="text-muted" data-translate-key="login_subtitle">Login to access your dashboard.</p>
                        <?php if(isset($_GET['status']) && $_GET['status'] == 'registration_success'): ?>
                            <div class="alert alert-success" data-translate-key="registration_success_msg">Registration successful! Please login.</div>
                        <?php endif; ?>
                        <?php if(isset($_GET['status']) && $_GET['status'] == 'password_reset_success'): ?>
                            <div class="alert alert-success" data-translate-key="password_reset_success_msg">Your password has been reset successfully. Please login.</div>
                        <?php endif; ?>
                        <?php if(isset($_GET['error'])): ?>
                            <div class="alert alert-danger" data-translate-key="invalid_credentials_msg">Invalid username or password.</div>
                        <?php endif; ?>
                    </div>

                    <form action="../src/actions/handle_login.php" method="POST">
                        <div class="input-group mb-4">
                            <span class="input-group-text"><i class="fa-solid fa-building"></i></span>
                            <input type="text" name="company_name" class="form-control" data-translate-key="company_name_ph" placeholder="Company Name" required>
                        </div>
                        <div class="input-group mb-4">
                            <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                            <input type="text" name="username" class="form-control" data-translate-key="username_ph" placeholder="Username" required>
                        </div>
                        <div class="input-group mb-4">
                            <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                            <input type="password" name="password" class="form-control" data-translate-key="password_ph" placeholder="Password" required>
                        </div>
                        <div class="text-end mb-4">
                            <a href="forgot_password.php" data-translate-key="forgot_password_link">Forgot Password?</a>
                        </div>
                        <div class="d-grid mb-4">
                            <button type="submit" class="btn btn-primary fw-bold" data-translate-key="login_btn">LOGIN</button>
                        </div>
                        <div class="text-center">
                            <p class="mb-0"><span data-translate-key="no_account_text">Don't have an account?</span> <a href="register.php" data-translate-key="register_now_link">Register Now</a></p>
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
