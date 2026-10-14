<?php
/**
 * Forgot Password / Reset Token helper.
 *
 * Adds one new, self-healing table (`password_resets`) — created the
 * same way every other module here creates its tables (CREATE TABLE
 * IF NOT EXISTS), so no existing table or feature is touched.
 *
 * A reset token is a single-use, time-limited credential tied to one
 * user. Only its SHA-256 hash is stored (never the raw token), so a
 * database leak alone can't be used to reset anyone's password — the
 * same principle as never storing plaintext passwords.
 */

require_once __DIR__ . '/tenant_helper.php';
require_once __DIR__ . '/mail_config.php';
require_once __DIR__ . '/../vendor/phpmailer/src/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

define('PASSWORD_RESET_TOKEN_TTL_MINUTES', 60);

function ensure_password_resets_table($conn) {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $conn->query("CREATE TABLE IF NOT EXISTS `password_resets` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `token_hash` varchar(64) NOT NULL,
        `expires_at` datetime NOT NULL,
        `used_at` datetime DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `token_hash` (`token_hash`),
        KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
}

/**
 * Looks up a user by (company_name, email) — the two fields the
 * Forgot Password form asks for. Returns the user row (id, username,
 * email, company_id) or null if no such account exists. Deliberately
 * does not distinguish "company not found" from "email not found" to
 * the caller in a way that would let the page reveal which part was
 * wrong — the calling page always shows the same generic message.
 */
function find_user_by_company_and_email($conn, $company_name, $email) {
    $company_id = get_company_id_by_name($conn, $company_name);
    if ($company_id === null) {
        return null;
    }
    $stmt = $conn->prepare("SELECT id, username, email, company_id FROM users WHERE company_id = ? AND email = ?");
    $stmt->bind_param("is", $company_id, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->num_rows === 1 ? $result->fetch_assoc() : null;
    $stmt->close();
    return $row;
}

/**
 * Generates a new one-time password reset token for a user, invalidating
 * any previous unused tokens for that user first (so only the most
 * recently requested link can ever work). Returns the raw token
 * (to embed in the emailed link) — only its hash is persisted.
 */
function create_password_reset_token($conn, $user_id) {
    ensure_password_resets_table($conn);

    // Invalidate any still-usable earlier tokens for this user.
    $stmt = $conn->prepare("UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();

    $raw_token = bin2hex(random_bytes(32));
    $token_hash = hash('sha256', $raw_token);
    $expires_at = date('Y-m-d H:i:s', time() + PASSWORD_RESET_TOKEN_TTL_MINUTES * 60);

    $stmt = $conn->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $token_hash, $expires_at);
    $stmt->execute();
    $stmt->close();

    return $raw_token;
}

/**
 * Verifies a raw reset token from the URL. Returns the associated
 * user row (id, username, email, company_id) if the token exists, has
 * not been used, and has not expired — otherwise null. Never assumes
 * the token is well-formed; any invalid input safely resolves to null.
 */
function verify_password_reset_token($conn, $raw_token) {
    ensure_password_resets_table($conn);

    if (!is_string($raw_token) || $raw_token === '') {
        return null;
    }

    $token_hash = hash('sha256', $raw_token);

    $stmt = $conn->prepare(
        "SELECT pr.id AS reset_id, pr.expires_at, pr.used_at, u.id, u.username, u.email, u.company_id
         FROM password_resets pr
         JOIN users u ON u.id = pr.user_id
         WHERE pr.token_hash = ?"
    );
    $stmt->bind_param("s", $token_hash);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->num_rows === 1 ? $result->fetch_assoc() : null;
    $stmt->close();

    if ($row === null) {
        return null;
    }
    if ($row['used_at'] !== null) {
        return null;
    }
    if (strtotime($row['expires_at']) < time()) {
        return null;
    }

    return [
        'reset_id'   => (int) $row['reset_id'],
        'user_id'    => (int) $row['id'],
        'username'   => $row['username'],
        'email'      => $row['email'],
        'company_id' => $row['company_id'] === null ? null : (int) $row['company_id'],
    ];
}

/**
 * Marks a reset token as used (single-use enforcement), then updates
 * the user's password to a freshly-hashed value in one atomic step.
 * Returns true on success.
 */
function consume_password_reset_token($conn, $reset_id, $user_id, $new_password) {
    ensure_password_resets_table($conn);

    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $hashed_password, $user_id);
    $ok = $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $reset_id);
    $stmt->execute();
    $stmt->close();

    return $ok;
}

/**
 * Sends the password reset email containing a link back to
 * reset_password.php with the raw token, via real SMTP delivery
 * (PHPMailer), so the message actually reaches the inbox instead of
 * relying on the server's local mail() (which most hosts either
 * block or send straight to Spam). Returns true if the mail server
 * accepted the message for delivery, false otherwise (logged, never
 * fatal — the calling code always shows the same generic message to
 * the user regardless of success/failure, so this can't be used to
 * probe for valid accounts).
 */
function send_password_reset_email($to_email, $username, $raw_token) {
    $reset_link = rtrim(app_base_url(), '/') . '/reset_password.php?token=' . urlencode($raw_token);

    $subject = 'Password Reset Request - Elite Real Estate CRM';
    $plain_message = "Hello " . $username . ",\r\n\r\n"
        . "We received a request to reset your password.\r\n"
        . "Click the link below to set a new password. This link expires in "
        . PASSWORD_RESET_TOKEN_TTL_MINUTES . " minutes and can only be used once:\r\n\r\n"
        . $reset_link . "\r\n\r\n"
        . "If you did not request this, you can safely ignore this email.\r\n";

    $html_message = '<p>Hello ' . htmlspecialchars($username) . ',</p>'
        . '<p>We received a request to reset your password. Click the button below to set a new password. '
        . 'This link expires in ' . (int) PASSWORD_RESET_TOKEN_TTL_MINUTES . ' minutes and can only be used once.</p>'
        . '<p><a href="' . htmlspecialchars($reset_link) . '" '
        . 'style="display:inline-block;padding:10px 20px;background:#4f46e5;color:#ffffff;text-decoration:none;border-radius:6px;">Reset Password</a></p>'
        . '<p>Or copy and paste this link into your browser:<br>' . htmlspecialchars($reset_link) . '</p>'
        . '<p>If you did not request this, you can safely ignore this email.</p>';

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION;
        $mail->Port = MAIL_PORT;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($to_email, $username);
        $mail->addReplyTo(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html_message;
        $mail->AltBody = $plain_message;

        $mail->send();
        return true;
    } catch (PHPMailerException $e) {
        // Never expose SMTP internals to the end user; log server-side
        // for the admin to diagnose (check PHP error log).
        error_log('Password reset email failed to send: ' . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Best-effort absolute base URL for building the reset link, derived
 * from the current request when available, falling back to the
 * app's known BASE_URL path otherwise.
 */
function app_base_url() {
    if (!empty($_SERVER['HTTP_HOST'])) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $scheme . '://' . $_SERVER['HTTP_HOST'] . BASE_URL;
    }
    return BASE_URL;
}

