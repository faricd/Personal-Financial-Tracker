<?php
// Start page: logged-in users go to the dashboard, everyone else to the login page
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
} else {
    header("Location: login.php");
}
exit;
