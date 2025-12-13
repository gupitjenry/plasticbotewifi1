<?php
session_start();

// Logout
$_SESSION = array();
session_destroy();
header('Location: admin-login.php');
exit;
?>
