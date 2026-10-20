
<?php
/*
 * FinTrack - Export Transactions to CSV
 * Supports English, Dari, and Pashto.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/transaction_functions.php';
require_once __DIR__ . '/../includes/language.php';

requireLogin();

// Read the same filters used on the Transactions page
$filters = readTransactionFilters($_GET);

// Build the filtered query
[$sql, $types, $params] = buildTransactionQuery(
    (int) $_SESSION['user_id'],
    $filters
);

// Fetch filtered transactions
$rows = fetchAll($conn, $sql, $types, $params);

// Download response headers
header('Content-Type: text/csv; charset=utf-8');

header(
    'Content-Disposition: attachment; filename="fintrack-transactions-'
    . date('Y-m-d') . '.csv"'
);

// Prevent caching
header('Cache-Control: no-store, no-cache');

// Create CSV output
$output = fopen('php://output', 'w');

// UTF-8 BOM helps Excel display Dari and Pashto text correctly
fwrite($output, "\xEF\xBB\xBF");

// Translated column headings
fputcsv($output, [
    t('date'),
    t('type'),
    t('category'),
    t('description'),
    t('payment_method'),
    t('amount'),
]);

// Export transaction rows
foreach ($rows as $row) {

    // Translate transaction type
    $type = $row['type'] === 'income'
        ? t('income')
        : t('expenses');

    // Translate payment method
    $paymentKey = 'payment_' . $row['payment_method'];

    $paymentMethod = t($paymentKey);

    fputcsv($output, [
        $row['transaction_date'],
        $type,
        $row['category_name'],
        $row['description'],
        $paymentMethod,
        $row['amount'],
    ]);
}

fclose($output);
exit;
