<?php
session_start();

// Check if user is logged in
// Check if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: login.html ');
    exit;
}
?>  