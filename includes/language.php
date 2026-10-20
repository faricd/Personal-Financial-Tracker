
<?php

/*
 * FinTrack Language System
 * Supported languages:
 * en = English
 * fa = Dari
 * ps = Pashto
 */

// Start session if needed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Supported languages
$allowedLanguages = ['en', 'fa', 'ps'];

// Change language through URL
if (
    isset($_GET['lang']) &&
    is_string($_GET['lang']) &&
    in_array($_GET['lang'], $allowedLanguages, true)
) {
    $_SESSION['lang'] = $_GET['lang'];
}

// Read selected language
$lang = $_SESSION['lang'] ?? 'en';

// Validate session language
if (
    !is_string($lang) ||
    !in_array($lang, $allowedLanguages, true)
) {
    $lang = 'en';
    $_SESSION['lang'] = 'en';
}

// Load English as the fallback language
$fallbackTranslations = require __DIR__ . '/../lang/en.php';

// Load selected language
if ($lang === 'en') {
    $translations = $fallbackTranslations;
} else {
    $translations = require __DIR__ . '/../lang/' . $lang . '.php';
}

// Translate a text key
if (!function_exists('t')) {
    function t(string $key): string
    {
        global $translations, $fallbackTranslations;

        return $translations[$key]
            ?? $fallbackTranslations[$key]
            ?? $key;
    }
}
