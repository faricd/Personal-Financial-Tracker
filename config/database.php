<?php
/*
 * Database connection (XAMPP)
 * ---------------------------
 * XAMPP's default MySQL account is "root" with an EMPTY password.
 * If you changed your MySQL password in XAMPP, update DB_PASS below.
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'financial_tracker');
define('DB_PORT', 3306);

// Make mysqli throw exceptions on errors (default in PHP 8.1+, set here for older XAMPP versions)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    $conn->set_charset('utf8mb4');   // supports symbols like ৳ and €
} catch (mysqli_sql_exception $e) {
    // Friendly message instead of a long PHP error
    die('<div style="font-family:sans-serif;padding:40px">
            <h2>Could not connect to the database</h2>
            <p>Make sure <b>MySQL is started</b> in the XAMPP Control Panel and that
               you imported <b>database/financial_tracker.sql</b> in phpMyAdmin.</p>
         </div>');
}
