<?php
/*
 * Session + security helpers.
 * Include this at the top of every page (after config/database.php).
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/functions.php';

// Create one random CSRF token per session (used to protect POST forms)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Send visitors who are not logged in back to the login page
function requireLogin(): void
{
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
}

// Stop the request if the CSRF token is missing or wrong
function verifyCsrfToken(): void
{
    $token = $_POST['csrf_token'] ?? '';

    if ($token === '' || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit("Invalid security token. Please go back and refresh the page.");
    }
}
