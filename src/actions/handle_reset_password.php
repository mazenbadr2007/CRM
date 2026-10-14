<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../password_reset_helper.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../../public/forgot_password.php");
    exit();
}

$raw_token = $_POST['token'] ?? '';
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// Re-verify the token server-side — never trust that the form was
// reached legitimately just because it was submitted. Handles both a
// tampered/unknown token and one that expired between page load and
// submission.
$reset_user = verify_password_reset_token($conn, $raw_token);
if ($reset_user === null) {
    header("Location: ../../public/reset_password.php?token=" . urlencode($raw_token) . "&error=invalid_token");
    exit();
}

if ($password === '' || $confirm_password === '') {
    header("Location: ../../public/reset_password.php?token=" . urlencode($raw_token) . "&error=empty_fields");
    exit();
}

if (strlen($password) < 8) {
    header("Location: ../../public/reset_password.php?token=" . urlencode($raw_token) . "&error=password_too_short");
    exit();
}

if ($password !== $confirm_password) {
    header("Location: ../../public/reset_password.php?token=" . urlencode($raw_token) . "&error=password_mismatch");
    exit();
}

// Hash-and-save the new password, then invalidate the token so it
// can never be reused (single-use enforcement).
consume_password_reset_token($conn, $reset_user['reset_id'], $reset_user['user_id'], $password);

header("Location: ../../public/login.php?status=password_reset_success");
exit();
?>
