
<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/transaction_functions.php';
require_once __DIR__ . '/../includes/language.php';

requireLogin();

$userId = (int) $_SESSION["user_id"];
$id = (int) ($_GET["id"] ?? 0);
$error = "";

// Load the transaction belonging to this user
$data = fetchOne(
    $conn,
    "SELECT *
     FROM transactions
     WHERE id = ? AND user_id = ?",
    "ii",
    [$id, $userId]
);

// Transaction not found
if (!$data) {

    setFlash(
        "danger",
        t('transaction_not_found')
    );

    header("Location: transactions.php");
    exit;
}

// Update transaction
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    verifyCsrfToken();

    [$data, $error] = validateTransaction(
        $conn,
        $userId,
        $_POST
    );

    if ($error === "") {

        $stmt = $conn->prepare(
            "UPDATE transactions
             SET
                category_id = ?,
                amount = ?,
                type = ?,
                payment_method = ?,
                description = ?,
                transaction_date = ?
             WHERE id = ? AND user_id = ?"
        );

        // Keep the same parameter types as your original file:
        // integer, double, 4 strings, 2 integers
        $stmt->bind_param(
            "idssssii",
            $data['category_id'],
            $data['amount'],
            $data['type'],
            $data['payment_method'],
            $data['description'],
            $data['transaction_date'],
            $id,
            $userId
        );

        $stmt->execute();

        setFlash(
            "success",
            t('transaction_updated_success')
        );

        header("Location: transactions.php");
        exit;
    }
}

// Load categories
$categories = fetchAll(
    $conn,
    "SELECT id, name, type
     FROM categories
     WHERE user_id = ?
     ORDER BY name",
    "i",
    [$userId]
);

// Page configuration
$pageTitle = t('edit_transaction') . " | FinTrack";
$activePage = "transactions";
$submitLabel = e(t('update_transaction'));

include __DIR__ . '/../includes/header.php';

?>

<!-- Page Header -->
<div class="container page-header">

    <a href="transactions.php"
       class="small text-decoration-none">

        <i class="bi <?= $isRTL ? 'bi-arrow-right' : 'bi-arrow-left' ?>"></i>

        <?= e(t('back_to_transactions')) ?>

    </a>

    <h1 class="h2 fw-bold mt-2">
        <?= e(t('edit_transaction')) ?>
    </h1>

</div>

<!-- Edit Transaction Form -->
<div class="container pb-4">

    <div class="card p-4 p-md-5 mx-auto form-card">

        <?php if ($error): ?>

            <div class="alert alert-danger">

                <i class="bi bi-exclamation-triangle me-1"></i>

                <?= e($error) ?>

            </div>

        <?php endif; ?>

        <?php
            include __DIR__ . '/../includes/transaction_form.php';
        ?>

    </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
