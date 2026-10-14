<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Elite Real Estate CRM</title>

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
                        <h1 class="fw-bold" data-translate-key="register_welcome_title">Create Account</h1>
                        <p class="text-muted" data-translate-key="register_subtitle">Get started with your new CRM.</p>
                        <?php if(isset($_GET['error'])): ?>
                            <div class="alert alert-danger mt-3"
                                 <?php
                                    $error_key = 'error_generic_fields';
                                    if ($_GET['error'] == 'reserved_username') $error_key = 'error_reserved_username';
                                    elseif ($_GET['error'] == 'username_taken') $error_key = 'error_username_taken';
                                    elseif ($_GET['error'] == 'email_taken') $error_key = 'error_email_taken';
                                    elseif ($_GET['error'] == 'invalid_email') $error_key = 'error_invalid_email';
                                    elseif ($_GET['error'] == 'password_mismatch') $error_key = 'error_password_mismatch';
                                    elseif ($_GET['error'] == 'user_limit_reached') $error_key = 'error_user_limit_reached';
                                 ?>
                                 data-translate-key="<?php echo $error_key; ?>">
                                <?php
                                    if ($_GET['error'] == 'reserved_username') echo 'This username is reserved.';
                                    elseif ($_GET['error'] == 'username_taken') echo 'Username is already taken.';
                                    elseif ($_GET['error'] == 'email_taken') echo 'This email is already in use.';
                                    elseif ($_GET['error'] == 'invalid_email') echo 'Please enter a valid email address.';
                                    elseif ($_GET['error'] == 'password_mismatch') echo 'Passwords do not match.';
                                    elseif ($_GET['error'] == 'user_limit_reached') echo 'Your plan has reached the maximum number of users. Please upgrade your subscription.';
                                    else echo 'Please fill all fields correctly.';
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <form action="../src/actions/handle_register.php" method="POST">
                        <div class="input-group mb-4">
                            <span class="input-group-text"><i class="fa-solid fa-building"></i></span>
                            <input type="text" name="company_name" class="form-control" data-translate-key="company_name_ph" placeholder="Company Name (optional)">
                        </div>
                        <div class="input-group mb-4">
                            <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                            <input type="text" name="username" class="form-control" data-translate-key="username_ph" placeholder="Username" required>
                        </div>
                        <div class="input-group mb-4">
                            <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                            <input type="email" name="email" class="form-control" data-translate-key="email_ph" placeholder="Email Address" required>
                        </div>
                        <div class="input-group mb-4">
                            <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                            <input type="password" name="password" class="form-control" data-translate-key="create_password_ph" placeholder="Create Password" required>
                        </div>
                        <div class="input-group mb-4">
                            <span class="input-group-text"><i class="fa-solid fa-check-double"></i></span>
                            <input type="password" name="confirm_password" class="form-control" data-translate-key="confirm_password_ph" placeholder="Confirm Password" required>
                        </div>
                        <div class="d-grid mb-4">
                            <button type="submit" class="btn btn-primary fw-bold" data-translate-key="register_btn">REGISTER</button>
                        </div>
                        <div class="text-center">
                            <p class="mb-0"><span data-translate-key="have_account_text">Already have an account?</span> <a href="login.php" data-translate-key="login_now_link">Login</a></p>
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
