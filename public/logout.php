<?php
// Clear all session data and go back to the login page
session_start();
$_SESSION = [];
session_destroy();

header("Location: login.php?logged_out=1");
exit;
