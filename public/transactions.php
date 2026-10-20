
<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/transaction_functions.php';
require_once __DIR__ . '/../includes/language.php';

requireLogin();

$userId = (int) $_SESSION["user_id"];
$filters = readTransactionFilters($_GET);

// Get transactions using existing filters
[$sql, $types, $params] = buildTransactionQuery($userId, $filters);
$transactions = fetchAll($conn, $sql, $types, $params);

// Calculate filtered totals
$totalIncome = $totalExpense = 0;

foreach ($transactions as $transaction) {
    if ($transaction['type'] === 'income') {
        $totalIncome += $transaction['amount'];
    } else {
        $totalExpense += $transaction['amount'];
    }
}

// Load categories for filters
$categories = fetchAll(
    $conn,
    "SELECT id, name, type
     FROM categories
     WHERE user_id = ?
     ORDER BY type, name",
    "i",
    [$userId]
);

// Page settings
$pageTitle = t('transactions') . " | FinTrack";
$activePage = "transactions";

include __DIR__ . '/../includes/header.php';

?>

<!-- Page Heading -->
<div class="container page-header">

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">

        <div>
            <h1 class="h2 fw-bold mb-1">
                <?= e(t('transactions')) ?>
            </h1>

            <p class="text-muted mb-0">
                <?= e(t('transactions_description')) ?>
            </p>
        </div>

        <div class="d-flex gap-2">

            <!-- Export CSV -->
            <a
                href="export_csv.php?<?= e(http_build_query($filters)) ?>"
                class="btn btn-outline-success"
                id="exportLink">

                <i class="bi bi-filetype-csv me-1"></i>
                <?= e(t('export_csv')) ?>

            </a>

            <!-- Add Transaction -->
            <a href="add_transaction.php" class="btn btn-primary">

                <i class="bi bi-plus-lg me-1"></i>
                <?= e(t('add_transaction')) ?>

            </a>

        </div>

    </div>

</div>


<div class="container pb-4">

    <!-- Transactions Filter Form -->
    <div class="card p-4 mb-4">

        <form
            class="row g-3"
            id="filterForm"
            method="GET">

            <!-- Search -->
            <div class="col-md-4">

                <label class="form-label">
                    <?= e(t('search')) ?>
                </label>

                <div class="input-group">

                    <span class="input-group-text">
                        <i class="bi bi-search"></i>
                    </span>

                    <input
                        name="search"
                        id="liveSearch"
                        value="<?= e($filters['search']) ?>"
                        class="form-control"
                        placeholder="<?= e(t('search_placeholder')) ?>"
                        autocomplete="off">

                </div>
            </div>

            <!-- Transaction Type -->
            <div class="col-6 col-md-2">

                <label class="form-label">
                    <?= e(t('type')) ?>
                </label>

                <select name="type" class="form-select">

                    <option value="">
                        <?= e(t('all')) ?>
                    </option>

                    <option
                        value="income"
                        <?= $filters['type'] === 'income' ? 'selected' : '' ?>>
                        <?= e(t('income')) ?>
                    </option>

                    <option
                        value="expense"
                        <?= $filters['type'] === 'expense' ? 'selected' : '' ?>>
                        <?= e(t('expenses')) ?>
                    </option>

                </select>
            </div>

            <!-- Category -->
            <div class="col-6 col-md-3">

                <label class="form-label">
                    <?= e(t('category')) ?>
                </label>

                <select name="category" class="form-select">

                    <option value="0">
                        <?= e(t('all_categories')) ?>
                    </option>

                    <?php foreach ($categories as $category): ?>

                        <option
                            value="<?= (int) $category['id'] ?>"
                            <?= $filters['category'] === (int) $category['id'] ? 'selected' : '' ?>>

                            <?= e($category['name']) ?>
                            (
                            <?= e(t($category['type'] === 'income' ? 'income' : 'expenses')) ?>
                            )

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <!-- Payment Method -->
            <div class="col-md-3">

                <label class="form-label">
                    <?= e(t('payment_method')) ?>
                </label>

                <select name="method" class="form-select">

                    <option value="">
                        <?= e(t('all_methods')) ?>
                    </option>

                    <?php foreach (paymentMethods() as $value => $label): ?>

                        <option
                            value="<?= e($value) ?>"
                            <?= $filters['method'] === $value ? 'selected' : '' ?>>

                            <?= e(t('payment_' . $value)) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <!-- From Date -->
            <div class="col-6 col-md-3">

                <label class="form-label">
                    <?= e(t('from')) ?>
                </label>

                <input
                    type="date"
                    name="from"
                    value="<?= e($filters['from']) ?>"
                    class="form-control date-from">

            </div>

            <!-- To Date -->
            <div class="col-6 col-md-3">

                <label class="form-label">
                    <?= e(t('to')) ?>
                </label>

                <input
                    type="date"
                    name="to"
                    value="<?= e($filters['to']) ?>"
                    class="form-control date-to">

            </div>

            <!-- Filter Buttons -->
            <div class="col-md-6 d-flex align-items-end gap-2">

                <button class="btn btn-dark">

                    <i class="bi bi-funnel me-1"></i>
                    <?= e(t('apply_filters')) ?>

                </button>

                <a href="transactions.php" class="btn btn-light">
                    <?= e(t('reset')) ?>
                </a>

            </div>

        </form>

    </div>


    <!-- Filtered Transaction Summary -->
    <div class="row g-3 mb-3">

        <!-- Total Results -->
        <div class="col-4">
            <div class="mini-stat">

                <span><?= e(t('results')) ?></span>

                <b id="resultCount">
                    <?= count($transactions) ?>
                </b>

            </div>
        </div>

        <!-- Total Income -->
        <div class="col-4">
            <div class="mini-stat">

                <span><?= e(t('income')) ?></span>

                <b class="amount-income" id="resultIncome">
                    <?= money($totalIncome) ?>
                </b>

            </div>
        </div>

        <!-- Total Expenses -->
        <div class="col-4">
            <div class="mini-stat">

                <span><?= e(t('expenses')) ?></span>

                <b class="amount-expense" id="resultExpense">
                    <?= money($totalExpense) ?>
                </b>

            </div>
        </div>

    </div>


    <!-- Transactions Table -->
    <div class="card p-4">

        <div class="table-responsive">

            <table class="table align-middle mb-0">

                <thead>
                    <tr>

                        <th>
                            <?= e(t('date')) ?>
                        </th>

                        <th>
                            <?= e(t('description')) ?>
                        </th>

                        <th>
                            <?= e(t('category')) ?>
                        </th>

                        <th>
                            <?= e(t('method')) ?>
                        </th>

                        <th>
                            <?= e(t('amount')) ?>
                        </th>

                        <th class="text-end">
                            <?= e(t('actions')) ?>
                        </th>

                    </tr>
                </thead>

                <tbody id="transactionRows">

                    <?php
                        renderTransactionRows($transactions);
                    ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
