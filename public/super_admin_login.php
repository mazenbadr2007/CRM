<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Login - Elite Real Estate CRM</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/rtl.css">
</head>
<body>

    <div class="container-fluid p-0">
        <div class="row g-0">
            <div class="col-lg-5 d-flex flex-column justify-content-center align-items-center bg-white form-column-wrapper mx-auto">
                <div class="form-container">
                    <div class="text-center mb-5">
                        <i class="fa-solid fa-user-shield" style="font-size: 2.5rem; color: #212529;"></i>
                        <h1 class="fw-bold mt-3">Super Admin</h1>
                        <p class="text-muted">Sign in to manage companies and subscriptions.</p>
                        <?php if (isset($_GET['error'])): ?>
                            <div class="alert alert-danger">Invalid username or password.</div>
                        <?php endif; ?>
                    </div>

                    <form action="../src/actions/handle_super_admin_login.php" method="POST">
                        <div class="input-group mb-4">
                            <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                            <input type="text" name="username" class="form-control" placeholder="Username" required>
                        </div>
                        <div class="input-group mb-4">
                            <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                            <input type="password" name="password" class="form-control" placeholder="Password" required>
                        </div>
                        <div class="d-grid mb-4">
                            <button type="submit" class="btn btn-dark fw-bold">LOGIN</button>
                        </div>
                        <div class="text-center">
                            <a href="login.php">Back to CRM login</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
