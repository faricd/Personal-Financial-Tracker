
<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/transaction_functions.php';
require_once __DIR__ . '/../includes/language.php';

requireLogin();

$userId = (int) $_SESSION["user_id"];
$error = "";

// Default values for an empty form
$data = [
    'type' => ($_GET['type'] ?? '') === 'income'
        ? 'income'
        : 'expense',
    'category_id' => 0,
    'amount' => '',
    'payment_method' => 'cash',
    'description' => '',
    'transaction_date' => date('Y-m-d'),
];

// Save a new transaction
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    verifyCsrfToken();

    [$data, $error] = validateTransaction(
        $conn,
        $userId,
        $_POST
    );

    if ($error === "") {

        $stmt = $conn->prepare(
            "INSERT INTO transactions
             (
                user_id,
                category_id,
                amount,
                type,
                payment_method,
                description,
                transaction_date
             )
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "iidssss",
            $userId,
            $data['category_id'],
            $data['amount'],
            $data['type'],
            $data['payment_method'],
            $data['description'],
            $data['transaction_date']
        );

        $stmt->execute();

        // Translated success message
        setFlash(
            "success",
            t('transaction_added_success')
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
$pageTitle = t('add_transaction') . " | FinTrack";
$activePage = "transactions";
$submitLabel = e(t('save_transaction'));

include __DIR__ . '/../includes/header.php';

?>

<!-- Page Header -->
<div class="container page-header">

    <a
        href="transactions.php"
        class="small text-decoration-none">

        <i class="bi <?= $isRTL ? 'bi-arrow-right' : 'bi-arrow-left' ?>"></i>

        <?= e(t('back_to_transactions')) ?>

    </a>

    <h1 class="h2 fw-bold mt-2">
        <?= e(t('add_transaction')) ?>
    </h1>

</div>

<!-- Transaction Form -->
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

<?php
include __DIR__ . '/../includes/footer.php';
?>
