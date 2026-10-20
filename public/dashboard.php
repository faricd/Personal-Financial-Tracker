
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ai_helper.php';
requireLogin();

$userId = (int) $_SESSION["user_id"];
$thisMonth = date('Y-m');

$balance = getTotalBalance($conn, $userId);
$month = getMonthTotals($conn, $userId, $thisMonth);
$savingsRate = $month['income'] > 0
    ? round(($month['income'] - $month['expense']) / $month['income'] * 100)
    : 0;

// Chart 1: Income vs expenses
$chartLabels = $chartIncome = $chartExpense = [];

for ($i = 5; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("first day of -$i month"));
    $totals = getMonthTotals($conn, $userId, $m);

    $chartLabels[] = date('M', strtotime($m . '-01'));
    $chartIncome[] = $totals['income'];
    $chartExpense[] = $totals['expense'];
}

// Chart 2: Category spending
$categorySpending = getCategorySpending($conn, $userId, $thisMonth);

// Budgets, goals and FinBot
$budgets = getBudgetStatus($conn, $userId, $thisMonth);
$goals = array_slice(getGoals($conn, $userId), 0, 3);
$insights = generateInsights($conn, $userId, $_SESSION['user_type']);
$health = calculateHealthScore($conn, $userId);

// Recent transactions
$recent = fetchAll(
    $conn,
    "SELECT t.*, c.name AS category_name, c.icon
     FROM transactions t
     JOIN categories c ON c.id = t.category_id
     WHERE t.user_id = ?
     ORDER BY t.transaction_date DESC, t.id DESC
     LIMIT 6",
    "i",
    [$userId]
);

// Language helper is loaded through header.php.
$pageTitle = "Dashboard | FinTrack";
$activePage = "dashboard";
$loadCharts = true;

include __DIR__ . '/../includes/header.php';

// Localized month labels
$months = [
    'en' => [
        'January','February','March','April','May','June',
        'July','August','September','October','November','December'
    ],
    'fa' => [
        'جنوری','فبروری','مارچ','اپریل','می','جون',
        'جولای','اگست','سپتمبر','اکتوبر','نومبر','دسمبر'
    ],
    'ps' => [
        'جنوري','فبروري','مارچ','اپرېل','مۍ','جون',
        'جولای','اګست','سپتمبر','اکتوبر','نومبر','دسمبر'
    ]
];

$monthName = $months[$lang][(int) date('n') - 1];

$chartLabels = array_map(
    fn($label) => $months[$lang][
        (int) date('n', strtotime($label . ' 1')) - 1
    ],
    $chartLabels
);
?>

<!-- Page header -->
<div class="container page-header">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <p class="text-primary fw-semibold mb-1">
                <?= date('d') ?> <?= e($monthName) ?> <?= date('Y') ?>
            </p>

            <h1 class="h2 fw-bold mb-1">
                <?= e(t('hello')) ?>,
                <?= e(explode(' ', $_SESSION["user_name"])[0]) ?> 👋
            </h1>

            <p class="text-muted mb-0">
                <?= e(t('financial_summary')) ?>
                <?= e($monthName) ?>
            </p>
        </div>

        <div class="d-flex gap-2">
            <a href="reports.php" class="btn btn-outline-primary">
                <i class="bi bi-bar-chart-line me-1"></i>
                <?= e(t('reports')) ?>
            </a>

            <a href="add_transaction.php" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>
                <?= e(t('add_transaction')) ?>
            </a>
        </div>
    </div>
</div>

<div class="container pb-4">

    <!-- Statistics -->
    <div class="row g-3 g-lg-4 mb-4">

        <div class="col-6 col-lg-3">
            <div class="card stat-card p-3 p-md-4 h-100">
                <div class="stat-icon bg-primary-subtle text-primary mb-3">
                    <i class="bi bi-wallet2"></i>
                </div>

                <div class="text-muted small">
                    <?= e(t('balance')) ?>
                </div>

                <div class="stat-value <?= $balance < 0 ? 'amount-expense' : '' ?>">
                    <?= money($balance) ?>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card stat-card p-3 p-md-4 h-100">
                <div class="stat-icon bg-success-subtle text-success mb-3">
                    <i class="bi bi-arrow-down-left"></i>
                </div>

                <div class="text-muted small">
                    <?= e(t('income_this_month')) ?>
                </div>

                <div class="stat-value amount-income">
                    <?= money($month['income']) ?>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card stat-card p-3 p-md-4 h-100">
                <div class="stat-icon bg-danger-subtle text-danger mb-3">
                    <i class="bi bi-arrow-up-right"></i>
                </div>

                <div class="text-muted small">
                    <?= e(t('expenses_this_month')) ?>
                </div>

                <div class="stat-value amount-expense">
                    <?= money($month['expense']) ?>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card stat-card p-3 p-md-4 h-100">
                <div class="stat-icon bg-warning-subtle text-warning mb-3">
                    <i class="bi bi-piggy-bank"></i>
                </div>

                <div class="text-muted small">
                    <?= e(t('savings_rate')) ?>
                </div>

                <div class="stat-value">
                    <?= $savingsRate ?>%
                </div>

                <div class="progress mt-2" style="height:6px">
                    <div class="progress-bar bg-warning"
                         style="width: <?= max(0, min(100, $savingsRate)) ?>%">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="row g-4 mb-4">

        <div class="col-lg-8">
            <div class="card p-4 h-100">
                <h2 class="h6 fw-bold mb-3">
                    <?= e(t('income_vs_expenses')) ?>
                </h2>
                <div class="chart-box">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card p-4 h-100">
                <h2 class="h6 fw-bold mb-3">
                    <?= e(t('spending_by_category')) ?>
                    (<?= e($monthName) ?>)
                </h2>

                <?php if ($categorySpending): ?>
                    <div class="chart-box">
                        <canvas id="categoryChart"></canvas>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="bi bi-pie-chart"></i>
                        <p><?= e(t('no_expenses')) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">

        <!-- FinBot -->
        <div class="col-lg-5">
            <div class="card finbot-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <span class="badge text-bg-light mb-2">
                            <i class="bi bi-robot me-1"></i>FinBot AI
                        </span>

                        <h2 class="h5 fw-bold mb-0">
                            <?= e(t('financial_health')) ?>
                        </h2>
                    </div>

                    <div class="score-ring" style="--score: <?= (int) $health['score'] ?>">
                        <span><?= (int) $health['score'] ?></span>
                    </div>
                </div>

                <p class="mb-3">
                    <?= e(t('your_score_is')) ?>
                    <b><?= e($health['label']) ?></b>.
                    <?= e(t('what_i_noticed')) ?>
                </p>

                <?php foreach (array_slice($insights, 0, 2) as $insight): ?>
                    <div class="insight-mini">
                        <i class="bi <?= e($insight['icon']) ?>"></i>
                        <div>
                            <b><?= e($insight['title']) ?></b>
                            <br>
                            <span><?= e($insight['text']) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>

                <a href="assistant.php" class="btn btn-light mt-auto align-self-start">
                    <?= e(t('see_all_insights')) ?>
                    <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>

        <!-- Budgets -->
        <div class="col-md-6 col-lg-4">
            <div class="card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h6 fw-bold mb-0">
                        <?= e(t('budgets_this_month')) ?>
                    </h2>

                    <a href="budgets.php" class="small">
                        <?= e(t('manage')) ?>
                    </a>
                </div>

                <?php if (!$budgets): ?>
                    <div class="empty-state">
                        <i class="bi bi-pie-chart"></i>
                        <p>
                            <?= e(t('no_budgets')) ?>
                            <a href="budgets.php">
                                <?= e(t('set_budget')) ?>
                            </a>
                        </p>
                    </div>
                <?php endif; ?>

                <?php foreach ($budgets as $b):
                    $color = $b['percent'] >= 100
                        ? 'danger'
                        : ($b['percent'] >= 80 ? 'warning' : 'success');
                ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span>
                                <i class="bi <?= e($b['icon']) ?> me-1"></i>
                                <?= e($b['name']) ?>
                            </span>

                            <span class="text-<?= $color ?> fw-semibold">
                                <?= $b['percent'] ?>%
                            </span>
                        </div>

                        <div class="progress" style="height:8px">
                            <div class="progress-bar bg-<?= $color ?>"
                                 style="width: <?= min(100, $b['percent']) ?>%">
                            </div>
                        </div>

                        <div class="small text-muted mt-1">
                            <?= money($b['spent']) ?>
                            <?= e(t('of')) ?>
                            <?= money($b['amount']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Savings goals -->
        <div class="col-md-6 col-lg-3">
            <div class="card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h6 fw-bold mb-0">
                        <?= e(t('goals')) ?>
                    </h2>

                    <a href="goals.php" class="small">
                        <?= e(t('manage')) ?>
                    </a>
                </div>

                <?php if (!$goals): ?>
                    <div class="empty-state">
                        <i class="bi bi-piggy-bank"></i>
                        <p>
                            <?= e(t('no_goals')) ?>
                            <a href="goals.php">
                                <?= e(t('create_goal')) ?>
                            </a>
                        </p>
                    </div>
                <?php endif; ?>

                <?php foreach ($goals as $g): ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="fw-semibold">
                                <?= e($g['title']) ?>
                            </span>
                            <span><?= $g['percent'] ?>%</span>
                        </div>

                        <div class="progress" style="height:8px">
                            <div class="progress-bar"
                                 style="width: <?= $g['percent'] ?>%">
                            </div>
                        </div>

                        <div class="small text-muted mt-1">
                            <?= money($g['saved_amount']) ?>
                            /
                            <?= money($g['target_amount']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Recent transactions -->
    <div class="card p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h6 fw-bold mb-0">
                <?= e(t('recent_transactions')) ?>
            </h2>

            <a href="transactions.php" class="btn btn-sm btn-outline-primary">
                <?= e(t('view_all')) ?>
            </a>
        </div>

        <?php if (!$recent): ?>
            <div class="empty-state">
                <i class="bi bi-receipt"></i>
                <p>
                    <?= e(t('no_transactions')) ?>
                    <a href="add_transaction.php">
                        <?= e(t('add_first_transaction')) ?>
                    </a>
                </p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th><?= e(t('date')) ?></th>
                            <th><?= e(t('description')) ?></th>
                            <th><?= e(t('categories')) ?></th>
                            <th class="text-end"><?= e(t('amount')) ?></th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($recent as $row): ?>
                            <tr>
                                <td class="text-nowrap">
                                    <?= date('d M Y', strtotime($row['transaction_date'])) ?>
                                </td>

                                <td>
                                    <?= e($row['description'] ?: '—') ?>
                                </td>

                                <td>
                                    <span class="cat-pill">
                                        <i class="bi <?= e($row['icon']) ?>"></i>
                                        <?= e($row['category_name']) ?>
                                    </span>
                                </td>

                                <td class="text-end text-nowrap <?= $row['type'] === 'income' ? 'amount-income' : 'amount-expense' ?>">
                                    <?= $row['type'] === 'income' ? '+' : '-' ?>
                                    <?= money($row['amount']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Chart.js -->
<script>
    new Chart(document.getElementById('trendChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>,
            datasets: [
                {
                    label: <?= json_encode(t('income'), JSON_UNESCAPED_UNICODE) ?>,
                    data: <?= json_encode($chartIncome) ?>,
                    backgroundColor: '#1f9d6b',
                    borderRadius: 6
                },
                {
                    label: <?= json_encode(t('expenses'), JSON_UNESCAPED_UNICODE) ?>,
                    data: <?= json_encode($chartExpense) ?>,
                    backgroundColor: '#e05252',
                    borderRadius: 6
                }
            ]
        },
        options: {
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });

    <?php if ($categorySpending): ?>
    new Chart(document.getElementById('categoryChart'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_column($categorySpending, 'name'), JSON_UNESCAPED_UNICODE) ?>,
            datasets: [{
                data: <?= json_encode(array_map('floatval', array_column($categorySpending, 'total'))) ?>,
                backgroundColor: chartColors()
            }]
        },
        options: {
            maintainAspectRatio: false,
            cutout: '62%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 12 }
                }
            }
        }
    });
    <?php endif; ?>
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
