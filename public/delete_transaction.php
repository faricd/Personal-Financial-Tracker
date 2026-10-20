
<?php
/*
 * FinTrack - Delete Transaction
 * Supports English, Dari, and Pashto.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/language.php';

requireLogin();

// Only allow deletion through POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: transactions.php");
    exit;
}

// Verify CSRF token
verifyCsrfToken();

// Get transaction and user IDs
$id = (int) ($_POST["id"] ?? 0);
$userId = (int) $_SESSION["user_id"];

// Delete only a transaction owned by this user
$stmt = $conn->prepare(
    "DELETE FROM transactions
     WHERE id = ? AND user_id = ?"
);

$stmt->bind_param("ii", $id, $userId);
$stmt->execute();

// Show translated messages
if ($stmt->affected_rows > 0) {

    setFlash(
        "success",
        t('transaction_deleted_success')
    );

} else {

    setFlash(
        "danger",
        t('transaction_not_found')
    );
}

// Return to transactions page
header("Location: transactions.php");
exit;
