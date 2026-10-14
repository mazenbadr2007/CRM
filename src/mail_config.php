<?php
/**
 * SMTP / Outgoing Mail Configuration.
 *
 * Fill in your own Gmail credentials below. For Gmail you must use an
 * "App Password" (not your normal Gmail password):
 *   1. Enable 2-Step Verification on the Google account:
 *      https://myaccount.google.com/security
 *   2. Create an App Password:
 *      https://myaccount.google.com/apppasswords
 *   3. Paste the 16-character app password into MAIL_PASSWORD below.
 *
 * Keep this file out of version control / do not expose it publicly —
 * it holds a live credential capable of sending mail as this account.
 */

define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_ENCRYPTION', 'tls'); // 'tls' for port 587, 'ssl' for port 465

define('MAIL_USERNAME', 'your_email@gmail.com');   // <-- your Gmail address
define('MAIL_PASSWORD', 'your_16_char_app_password'); // <-- Gmail App Password

define('MAIL_FROM_ADDRESS', MAIL_USERNAME);
define('MAIL_FROM_NAME', 'Elite Real Estate CRM');
