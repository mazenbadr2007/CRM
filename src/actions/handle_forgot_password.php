<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../tenant_helper.php';
require_once __DIR__ . '/../password_reset_helper.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../../public/forgot_password.php");
    exit();
}

$company_name = trim($_POST['company_name'] ?? '');
$email = trim($_POST['email'] ?? '');

if ($company_name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: ../../public/forgot_password.php?error=empty_fields");
    exit();
}

// Look up the account, but never reveal to the caller whether the
// company or the email was the part that didn't match — the response
// is identical either way, so this endpoint can't be used to probe
// for which companies/emails exist in the system.
$user = find_user_by_company_and_email($conn, $company_name, $email);

if ($user !== null) {
    $raw_token = create_password_reset_token($conn, $user['id']);
    send_password_reset_email($user['email'], $user['username'], $raw_token);
}

// Always redirect to the same "check your email" confirmation,
// whether or not an account was actually found.
header("Location: ../../public/forgot_password.php?sent=1");
exit();
?>
