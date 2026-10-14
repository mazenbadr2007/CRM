<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Session Debug</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f0f2f5; padding: 50px; font-family: monospace; }
        .card { max-width: 800px; margin: auto; }
        pre { background-color: #282c34; color: #abb2bf; padding: 20px; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="card-header">
            <h2>Session Data Diagnostics</h2>
        </div>
        <div class="card-body">
            <p>This page shows all the data currently stored in your session.</p>
            <pre><?php print_r($_SESSION); ?></pre>
            <hr>
            <h4>Analysis:</h4>
            <?php
                if (isset($_SESSION['is_manager'])) {
                    if ($_SESSION['is_manager'] === true) {
                        echo '<div class="alert alert-success"><strong>Success!</strong> The session correctly identifies you as a manager.</div>';
                    } else {
                        echo '<div class="alert alert-warning"><strong>Issue Found:</strong> You are logged in, but the session says you are NOT a manager (`is_manager` is false).</div>';
                    }
                } else {
                    echo '<div class="alert alert-danger"><strong>Major Issue Found!</strong> The `is_manager` variable is NOT SET in the session. The login script is not working as expected.</div>';
                }
            ?>
            <a href="home.php" class="btn btn-primary mt-3">Go to Home Page</a>
        </div>
    </div>
</body>
</html>