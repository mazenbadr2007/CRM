<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


define('BASE_URL', '/CRM/public'); 

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'crm_system';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

require_once __DIR__ . '/tenant_helper.php';
ensure_company_id_columns($conn);
?>