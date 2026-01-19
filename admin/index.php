<?php
// Main entry point - redirect to login or dashboard
session_start();

if (isset($_SESSION['admin_logged_in'])) {
    header('Location: core/admin_dashboard.php');
} else {
    header('Location: auth/admin_login.php');
}
exit();
?>
