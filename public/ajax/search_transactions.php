
<?php
/*
 * FinTrack AJAX Live Search
 * Supports English, Dari, and Pashto.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/transaction_functions.php';
require_once __DIR__ . '/../../includes/language.php';

header('Content-Type: application/json; charset=utf-8');

// Check authentication
if (!isset($_SESSION['user_id'])) {

    http_response_code(401);

    echo json_encode(
        ['error' => t('not_logged_in')],
        JSON_UNESCAPED_UNICODE
    );

    exit;
}

// Read the transaction filters
$filters = readTransactionFilters($_GET);

// Build the existing filtered query
[$sql, $types, $params] = buildTransactionQuery(
    (int) $_SESSION['user_id'],
    $filters
);

// Fetch matching transactions
$rows = fetchAll($conn, $sql, $types, $params);

// Calculate income and expense totals
$income = 0;
$expense = 0;

foreach ($rows as $row) {

    if ($row['type'] === 'income') {
        $income += $row['amount'];
    } else {
        $expense += $row['amount'];
    }
}

// Capture translated transaction table rows
ob_start();

renderTransactionRows($rows);

$html = ob_get_clean();

// Send response back to JavaScript
echo json_encode(
    [
        'html' => $html,
        'count' => count($rows),
        'income' => money($income),
        'expense' => money($expense),
    ],
    JSON_UNESCAPED_UNICODE
);

exit;
