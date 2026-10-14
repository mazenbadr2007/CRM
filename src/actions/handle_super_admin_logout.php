<?php
session_start();
unset($_SESSION['super_admin_id']);
unset($_SESSION['super_admin_username']);
header("Location: ../../public/super_admin_login.php");
exit();
?>
