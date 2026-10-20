
<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ai_helper.php';
require_once __DIR__ . '/../includes/language.php';

requireLogin();

$userId = (int) $_SESSION['user_id'];
$error = '';

// Localized Gregorian month label
if (!function_exists('budgetMonthLabel')) {
    function budgetMonthLabel(string $month): string
    {
        $timestamp = strtotime($month . '-01');

        return t('month_' . date('n', $timestamp))
            . ' ' . date('Y', $timestamp);
    }
}

// Selected month
$month = $_GET['month'] ?? date('Y-m');

if (!is_string($month) || !isValidMonth($month)) {
    $month = date('Y-m');
}

// Process submitted forms
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verifyCsrfToken();

    $action = $_POST['action'] ?? '';

    // Create or update a budget
    if ($action === 'save') {

        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $amountInput = $_POST['amount'] ?? '';

        $amount = is_scalar($amountInput)
            && is_numeric($amountInput)
            ? (float) $amountInput
            : 0;

        // Make sure category belongs to the user
        $category = fetchOne(
            $conn,
            "SELECT id
             FROM categories
             WHERE id = ?
               AND user_id = ?
               AND type = 'expense'",
            "ii",
            [$categoryId, $userId]
        );

        if (!$category) {

            $error = t('budget_choose_category');

        } elseif (
            !is_finite($amount) ||
            $amount < 0.01
        ) {

            $error = t('budget_invalid_amount');

        } else {

            $stmt = $conn->prepare(
                "INSERT INTO budgets
                 (user_id, category_id, budget_month, amount)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                 amount = VALUES(amount)"
            );

            $stmt->bind_param(
                "iisd",
                $userId,
                $categoryId,
                $month,
                $amount
            );

            $stmt->execute();

            setFlash(
                'success',
                t('budget_saved')
            );

            header(
                'Location: budgets.php?month=' . urlencode($month)
            );
            exit;
        }
    }

    // Delete a budget
    if ($action === 'delete') {

        $id = (int) ($_POST['id'] ?? 0);

        $stmt = $conn->prepare(
            "DELETE FROM budgets
             WHERE id = ? AND user_id = ?"
        );

        $stmt->bind_param(
            "ii",
            $id,
            $userId
        );

        $stmt->execute();

        setFlash(
            'success',
            t('budget_deleted')
        );

        header(
            'Location: budgets.php?month=' . urlencode($month)
        );
        exit;
    }

    // Copy previous month's budgets
    if ($action === 'copy') {

        $previous = date(
            'Y-m',
            strtotime($month . '-01 -1 month')
        );

        $stmt = $conn->prepare(
            "INSERT IGNORE INTO budgets
             (user_id, category_id, budget_month, amount)
             SELECT user_id, category_id, ?, amount
             FROM budgets
             WHERE user_id = ?
               AND budget_month = ?"
        );

        $stmt->bind_param(
            "sis",
            $month,
            $userId,
            $previous
        );

        $stmt->execute();

        $copiedCount = $stmt->affected_rows;

        // Translation expects exactly:
        // %d = number of copied budgets
        // %s = previous month
        $message = sprintf(
            t('budgets_copied'),
            $copiedCount,
            budgetMonthLabel($previous)
        );

        setFlash('success', $message);

        header(
            'Location: budgets.php?month=' . urlencode($month)
        );
        exit;
    }
}

// Read budgets
$budgets = getBudgetStatus(
    $conn,
    $userId,
    $month
);

// Read expense categories
$expenseCategories = fetchAll(
    $conn,
    "SELECT id, name
     FROM categories
     WHERE user_id = ?
       AND type = 'expense'
     ORDER BY name",
    "i",
    [$userId]
);

// Calculate totals
$totalBudget = array_sum(
    array_column($budgets, 'amount')
);

$totalSpent = array_sum(
    array_column($budgets, 'spent')
);

$totalRemaining = $totalBudget - $totalSpent;

// Find the selected budget for editing
$editBudget = null;

foreach ($budgets as $budget) {

    if (
        (int) ($_GET['edit'] ?? 0)
        === (int) $budget['id']
    ) {
        $editBudget = $budget;
        break;
    }
}

// Month navigation
$monthName = budgetMonthLabel($month);

$prevMonth = date(
    'Y-m',
    strtotime($month . '-01 -1 month')
);

$nextMonth = date(
    'Y-m',
    strtotime($month . '-01 +1 month')
);

// Page configuration
$pageTitle = t('budgets') . ' | FinTrack';
$activePage = 'budgets';

include __DIR__ . '/../includes/header.php';

?>

<!-- Page Header -->
<div class="container page-header">

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">

        <div>

            <h1 class="h2 fw-bold mb-1">
                <?= e(t('monthly_budgets')) ?>
            </h1>

            <p class="text-muted mb-0">
                <?= e(t('budgets_description')) ?>
            </p>

        </div>

        <!-- Month Navigation -->
        <div class="btn-group">

            <a
                href="?month=<?= e($prevMonth) ?>"
                class="btn btn-outline-primary"
                title="<?= e(t('previous_month')) ?>"
                aria-label="<?= e(t('previous_month')) ?>">

                <i class="bi <?= $isRTL
                    ? 'bi-chevron-right'
                    : 'bi-chevron-left' ?>"></i>

            </a>

            <span class="btn btn-primary disabled fw-semibold">
                <?= e($monthName) ?>
            </span>

            <a
                href="?month=<?= e($nextMonth) ?>"
                class="btn btn-outline-primary"
                title="<?= e(t('next_month')) ?>"
                aria-label="<?= e(t('next_month')) ?>">

                <i class="bi <?= $isRTL
                    ? 'bi-chevron-left'
                    : 'bi-chevron-right' ?>"></i>

            </a>

        </div>

    </div>

</div>


<div class="container pb-4">

    <div class="row g-4">

        <!-- Budget Form -->
        <div class="col-lg-4">

            <div class="card p-4 mb-4">

                <h2 class="h5 fw-bold mb-3">

                    <?= e(t(
                        $editBudget
                            ? 'edit_budget'
                            : 'set_budget'
                    )) ?>

                </h2>

                <?php if ($error): ?>

                    <div class="alert alert-danger">
                        <?= e($error) ?>
                    </div>

                <?php endif; ?>

                <form
                    method="POST"
                    class="needs-validation"
                    novalidate>

                    <?= csrfField() ?>

                    <input
                        type="hidden"
                        name="action"
                        value="save">

                    <!-- Expense Category -->
                    <div class="mb-3">

                        <label
                            class="form-label"
                            for="budgetCategory">

                            <?= e(t('expense_category')) ?>

                        </label>

                        <select
                            name="category_id"
                            id="budgetCategory"
                            class="form-select"
                            required>

                            <option value="">
                                <?= e(t('choose_category')) ?>
                            </option>

                            <?php foreach ($expenseCategories as $category): ?>

                                <option
                                    value="<?= (int) $category['id'] ?>"
                                    <?= $editBudget
                                        && (int) $editBudget['category_id']
                                        === (int) $category['id']
                                        ? 'selected'
                                        : '' ?>>

                                    <?= e($category['name']) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                        <div class="invalid-feedback">
                            <?= e(t('budget_choose_category')) ?>
                        </div>

                    </div>

                    <!-- Monthly Limit -->
                    <div class="mb-3">

                        <label
                            class="form-label"
                            for="budgetAmount">

                            <?= e(t('monthly_limit')) ?>

                            (<?= e($_SESSION['currency'] ?? '$') ?>)

                        </label>

                        <input
                            type="number"
                            name="amount"
                            id="budgetAmount"
                            class="form-control"
                            min="0.01"
                            step="0.01"
                            required
                            value="<?= $editBudget
                                ? e($editBudget['amount'])
                                : '' ?>">

                        <div class="invalid-feedback">
                            <?= e(t('budget_invalid_amount')) ?>
                        </div>

                    </div>

                    <!-- Save Budget -->
                    <button
                        type="submit"
                        class="btn btn-primary w-100">

                        <i class="bi bi-check2 me-1"></i>

                        <?= e(t('save_budget_for')) ?>

                        <?= e(budgetMonthLabel($month)) ?>

                    </button>

                    <?php if ($editBudget): ?>

                        <a
                            href="budgets.php?month=<?= e($month) ?>"
                            class="btn btn-light w-100 mt-2">

                            <?= e(t('cancel')) ?>

                        </a>

                    <?php endif; ?>

                </form>

            </div>

            <!-- Copy Previous Month -->
            <div class="card p-4">

                <h2 class="h6 fw-bold">
                    <?= e(t('quick_start')) ?>
                </h2>

                <p class="small text-muted">

                    <?= e(sprintf(
                        t('copy_budgets_description'),
                        budgetMonthLabel($prevMonth),
                        budgetMonthLabel($month)
                    )) ?>

                </p>

                <form method="POST">

                    <?= csrfField() ?>

                    <input
                        type="hidden"
                        name="action"
                        value="copy">

                    <button
                        type="submit"
                        class="btn btn-outline-primary w-100">

                        <i class="bi bi-copy me-1"></i>

                        <?= e(t('copy_last_month_budgets')) ?>

                    </button>

                </form>

            </div>

        </div>


        <!-- Budget List -->
        <div class="col-lg-8">

            <!-- Summary Cards -->
            <div class="row g-3 mb-4">

                <!-- Total Budget -->
                <div class="col-4">

                    <div class="mini-stat">

                        <span>
                            <?= e(t('total_budget')) ?>
                        </span>

                        <b>
                            <?= money($totalBudget) ?>
                        </b>

                    </div>

                </div>

                <!-- Spent -->
                <div class="col-4">

                    <div class="mini-stat">

                        <span>
                            <?= e(t('spent')) ?>
                        </span>

                        <b class="amount-expense">
                            <?= money($totalSpent) ?>
                        </b>

                    </div>

                </div>

                <!-- Remaining -->
                <div class="col-4">

                    <div class="mini-stat">

                        <span>
                            <?= e(t('remaining')) ?>
                        </span>

                        <b class="<?= $totalRemaining < 0
                            ? 'amount-expense'
                            : 'amount-income' ?>">

                            <?= money($totalRemaining) ?>

                        </b>

                    </div>

                </div>

            </div>


            <!-- Budget Listing -->
            <div class="card p-4">

                <?php if (!$budgets): ?>

                    <div class="empty-state">

                        <i class="bi bi-pie-chart"></i>

                        <p>

                            <?= e(sprintf(
                                t('no_budgets_for_month'),
                                $monthName
                            )) ?>

                        </p>

                    </div>

                <?php endif; ?>


                <?php foreach ($budgets as $budget): ?>

                    <?php
                    $percent = (float) $budget['percent'];

                    $color = $percent >= 100
                        ? 'danger'
                        : ($percent >= 80
                            ? 'warning'
                            : 'success');
                    ?>

                    <div class="budget-row">

                        <div class="d-flex justify-content-between align-items-center mb-2 gap-2">

                            <div class="d-flex align-items-center gap-2">

                                <!-- Category Icon -->
                                <span class="category-icon bg-<?= $color ?>-subtle text-<?= $color ?>">

                                    <i class="bi <?= e($budget['icon']) ?>"></i>

                                </span>

                                <!-- Category Details -->
                                <div>

                                    <div class="fw-semibold">

                                        <?= e($budget['name']) ?>

                                    </div>

                                    <div class="small text-muted">

                                        <?= money($budget['spent']) ?>

                                        <?= e(t('of')) ?>

                                        <?= money($budget['amount']) ?>

                                    </div>

                                </div>

                            </div>


                            <!-- Budget Actions -->
                            <div class="text-end">

                                <!-- Percentage -->
                                <span class="badge text-bg-<?= $color ?>">

                                    <?= e($budget['percent']) ?>%

                                </span>

                                <!-- Edit -->
                                <a
                                    href="?month=<?= e($month) ?>&amp;edit=<?= (int) $budget['id'] ?>"
                                    class="btn btn-sm btn-light ms-1"
                                    title="<?= e(t('edit')) ?>"
                                    aria-label="<?= e(t('edit')) ?>">

                                    <i class="bi bi-pencil"></i>

                                </a>

                                <!-- Delete -->
                                <form
                                    method="POST"
                                    class="d-inline delete-form">

                                    <?= csrfField() ?>

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="delete">

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int) $budget['id'] ?>">

                                    <button
                                        type="button"
                                        class="btn btn-sm btn-light text-danger delete-btn"
                                        data-title="<?= e(t('delete_budget_confirm')) ?>"
                                        title="<?= e(t('delete')) ?>"
                                        aria-label="<?= e(t('delete')) ?>">

                                        <i class="bi bi-trash"></i>

                                    </button>

                                </form>

                            </div>

                        </div>

                        <!-- Budget Progress -->
                        <div
                            class="progress"
                            style="height: 10px">

                            <div
                                class="progress-bar bg-<?= $color ?>"
                                style="width: <?= max(
                                    0,
                                    min(100, $percent)
                                ) ?>%">
                            </div>

                        </div>

                        <!-- Over-budget Warning -->
                        <?php if ($percent > 100): ?>

                            <div class="small text-danger mt-1">

                                <i class="bi bi-exclamation-octagon"></i>

                                <?= e(t('over_budget_by')) ?>

                                <?= money(
                                    $budget['spent']
                                    - $budget['amount']
                                ) ?>

                            </div>

                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>

            </div>

        </div>

    </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
