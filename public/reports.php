
<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/language.php';

requireLogin();

$userId = (int) $_SESSION["user_id"];
$error = "";

/*
 * Localized Gregorian dates.
 */
function reportDateLabel(string $date, bool $showYear = true): string
{
    $timestamp = strtotime($date);

    if ($timestamp === false) {
        return $date;
    }

    $result = date('d', $timestamp)
        . ' '
        . t('month_' . date('n', $timestamp));

    if ($showYear) {
        $result .= ' ' . date('Y', $timestamp);
    }

    return $result;
}

function reportPeriodLabel(string $period): string
{
    if (strlen($period) === 7) {
        return t('month_' . (int) substr($period, 5, 2))
            . ' ' . substr($period, 0, 4);
    }

    return reportDateLabel($period, false);
}

/* ---------- Date range ---------- */

$from = $_GET["from"] ?? date("Y-m-01");
$to = $_GET["to"] ?? date("Y-m-d");

if (!isValidDate($from) || !isValidDate($to)) {

    $error = t('report_invalid_date');

    $from = date("Y-m-01");
    $to = date("Y-m-d");

} elseif ($from > $to) {

    $error = t('report_dates_swapped');

    [$from, $to] = [$to, $from];
}

/* ---------- Quick ranges ---------- */

$quickRanges = [
    'report_this_month' => [
        date('Y-m-01'),
        date('Y-m-d')
    ],
    'report_last_month' => [
        date('Y-m-01', strtotime('first day of last month')),
        date('Y-m-t', strtotime('first day of last month'))
    ],
    'report_last_three_months' => [
        date('Y-m-01', strtotime('first day of -2 month')),
        date('Y-m-d')
    ],
    'report_this_year' => [
        date('Y-01-01'),
        date('Y-m-d')
    ],
];

/* ---------- 1. Totals ---------- */

$totals = fetchOne(
    $conn,
    "SELECT
        COALESCE(SUM(CASE WHEN type = 'income'
            THEN amount END), 0) AS income,
        COALESCE(SUM(CASE WHEN type = 'expense'
            THEN amount END), 0) AS expense,
        COUNT(*) AS cnt
     FROM transactions
     WHERE user_id = ?
       AND transaction_date BETWEEN ? AND ?",
    "iss",
    [$userId, $from, $to]
);

$net = $totals['income'] - $totals['expense'];

$days = (new DateTime($from))
    ->diff(new DateTime($to))->days + 1;

$dailyAvg = $totals['expense'] / $days;

/* ---------- 2. Category totals ---------- */

$byCategory = fetchAll(
    $conn,
    "SELECT
        c.name,
        c.icon,
        t.type,
        SUM(t.amount) AS total,
        COUNT(*) AS cnt
     FROM transactions t
     JOIN categories c ON c.id = t.category_id
     WHERE t.user_id = ?
       AND t.transaction_date BETWEEN ? AND ?
     GROUP BY c.id, c.name, c.icon, t.type
     ORDER BY total DESC",
    "iss",
    [$userId, $from, $to]
);

$expenseCategories = array_values(
    array_filter(
        $byCategory,
        fn($r) => $r['type'] === 'expense'
    )
);

$incomeCategories = array_values(
    array_filter(
        $byCategory,
        fn($r) => $r['type'] === 'income'
    )
);

/* ---------- 3. Spending trend ---------- */

$groupFormat = $days <= 31
    ? '%Y-%m-%d'
    : '%Y-%m';

$trend = fetchAll(
    $conn,
    "SELECT
        DATE_FORMAT(transaction_date, '$groupFormat') AS period,
        SUM(CASE WHEN type = 'income'
            THEN amount ELSE 0 END) AS income,
        SUM(CASE WHEN type = 'expense'
            THEN amount ELSE 0 END) AS expense
     FROM transactions
     WHERE user_id = ?
       AND transaction_date BETWEEN ? AND ?
     GROUP BY period
     ORDER BY period",
    "iss",
    [$userId, $from, $to]
);

/* ---------- 4. Payment methods ---------- */

$byMethod = fetchAll(
    $conn,
    "SELECT
        payment_method,
        SUM(amount) AS total
     FROM transactions
     WHERE user_id = ?
       AND type = 'expense'
       AND transaction_date BETWEEN ? AND ?
     GROUP BY payment_method
     ORDER BY total DESC",
    "iss",
    [$userId, $from, $to]
);

/* ---------- 5. Largest expenses ---------- */

$topExpenses = fetchAll(
    $conn,
    "SELECT
        t.*,
        c.name AS category_name
     FROM transactions t
     JOIN categories c ON c.id = t.category_id
     WHERE t.user_id = ?
       AND t.type = 'expense'
       AND t.transaction_date BETWEEN ? AND ?
     ORDER BY t.amount DESC
     LIMIT 5",
    "iss",
    [$userId, $from, $to]
);

/* ---------- Chart data ---------- */

$trendLabels = array_map(
    fn($period) => reportPeriodLabel($period),
    array_column($trend, 'period')
);

$chartIncome = array_map(
    'floatval',
    array_column($trend, 'income')
);

$chartExpenses = array_map(
    'floatval',
    array_column($trend, 'expense')
);

$categoryLabels = array_column(
    $expenseCategories,
    'name'
);

$categoryAmounts = array_map(
    'floatval',
    array_column($expenseCategories, 'total')
);

/* ---------- Page settings ---------- */

$pageTitle = t('reports') . " | FinTrack";
$activePage = "reports";
$loadCharts = true;

include __DIR__ . '/../includes/header.php';

?>

<!-- Page Header -->
<div class="container page-header">

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">

        <div>

            <h1 class="h2 fw-bold mb-1">
                <?= e(t('financial_report')) ?>
            </h1>

            <p class="text-muted mb-0">

                <?= e(reportDateLabel($from)) ?>

                –

                <?= e(reportDateLabel($to)) ?>

                (<?= e(sprintf(t('report_days'), $days)) ?>)

            </p>

        </div>

        <!-- Export and Print -->
        <div class="d-flex gap-2 no-print">

            <a
                href="export_csv.php?from=<?= e($from) ?>&to=<?= e($to) ?>"
                class="btn btn-outline-success">

                <i class="bi bi-filetype-csv me-1"></i>

                CSV

            </a>

            <button
                type="button"
                onclick="window.print()"
                class="btn btn-outline-primary">

                <i class="bi bi-printer me-1"></i>

                <?= e(t('print_pdf')) ?>

            </button>

        </div>

    </div>

</div>


<div class="container pb-4">

    <!-- Date Filters -->
    <div class="card p-4 mb-4 no-print">

        <?php if ($error): ?>

            <div class="alert alert-warning">
                <?= e($error) ?>
            </div>

        <?php endif; ?>

        <form
            class="row g-3 align-items-end needs-validation"
            id="reportForm"
            method="GET"
            novalidate>

            <!-- From Date -->
            <div class="col-6 col-md-4">

                <label class="form-label" for="reportFrom">
                    <?= e(t('from')) ?>
                </label>

                <input
                    type="date"
                    id="reportFrom"
                    name="from"
                    value="<?= e($from) ?>"
                    class="form-control date-from"
                    required>

            </div>

            <!-- To Date -->
            <div class="col-6 col-md-4">

                <label class="form-label" for="reportTo">
                    <?= e(t('to')) ?>
                </label>

                <input
                    type="date"
                    id="reportTo"
                    name="to"
                    value="<?= e($to) ?>"
                    class="form-control date-to"
                    required>

                <div class="invalid-feedback">
                    <?= e(t('date_range_invalid')) ?>
                </div>

            </div>

            <!-- Generate Report -->
            <div class="col-md-4">

                <button class="btn btn-primary w-100" type="submit">

                    <i class="bi bi-arrow-repeat me-1"></i>

                    <?= e(t('generate_report')) ?>

                </button>

            </div>

        </form>

        <!-- Quick Date Filters -->
        <div class="d-flex flex-wrap gap-2 mt-3">

            <?php foreach (
                $quickRanges as $labelKey => [$qFrom, $qTo]
            ): ?>

                <a
                    href="?from=<?= e($qFrom) ?>&to=<?= e($qTo) ?>"
                    class="btn btn-sm <?= $qFrom === $from && $qTo === $to
                        ? 'btn-primary'
                        : 'btn-light' ?>">

                    <?= e(t($labelKey)) ?>

                </a>

            <?php endforeach; ?>

        </div>

    </div>


    <!-- Summary Cards -->
    <div class="row g-3 g-lg-4 mb-4">

        <!-- Income -->
        <div class="col-6 col-lg-3">

            <div class="card p-4 h-100">

                <div class="text-muted small">
                    <?= e(t('income')) ?>
                </div>

                <div class="stat-value amount-income">
                    <?= money($totals['income']) ?>
                </div>

            </div>

        </div>

        <!-- Expenses -->
        <div class="col-6 col-lg-3">

            <div class="card p-4 h-100">

                <div class="text-muted small">
                    <?= e(t('expenses')) ?>
                </div>

                <div class="stat-value amount-expense">
                    <?= money($totals['expense']) ?>
                </div>

            </div>

        </div>

        <!-- Net Balance -->
        <div class="col-6 col-lg-3">

            <div class="card p-4 h-100">

                <div class="text-muted small">
                    <?= e(t('net_balance')) ?>
                </div>

                <div class="stat-value <?= $net < 0 ? 'amount-expense' : '' ?>">
                    <?= money($net) ?>
                </div>

            </div>

        </div>

        <!-- Daily Average -->
        <div class="col-6 col-lg-3">

            <div class="card p-4 h-100">

                <div class="text-muted small">
                    <?= e(t('average_daily_spending')) ?>
                </div>

                <div class="stat-value">
                    <?= money($dailyAvg) ?>
                </div>

                <div class="small text-muted">
                    <?= (int) $totals['cnt'] ?>
                    <?= e(t('transactions')) ?>
                </div>

            </div>

        </div>

    </div>


    <?php if ((int) $totals['cnt'] === 0): ?>

        <!-- Empty Report -->
        <div class="card p-4">

            <div class="empty-state">

                <i class="bi bi-bar-chart"></i>

                <p>
                    <?= e(t('report_no_transactions')) ?>
                </p>

            </div>

        </div>

    <?php else: ?>

        <!-- Charts -->
        <div class="row g-4 mb-4">

            <!-- Trend Chart -->
            <div class="col-lg-8">

                <div class="card p-4 h-100">

                    <h2 class="h6 fw-bold mb-3">
                        <?= e(t('income_expenses_trend')) ?>
                    </h2>

                    <div class="chart-box">
                        <canvas id="trendChart"></canvas>
                    </div>

                </div>

            </div>

            <!-- Category Chart -->
            <div class="col-lg-4">

                <div class="card p-4 h-100">

                    <h2 class="h6 fw-bold mb-3">
                        <?= e(t('expenses_by_category')) ?>
                    </h2>

                    <div class="chart-box">
                        <canvas id="categoryChart"></canvas>
                    </div>

                </div>

            </div>

        </div>


        <!-- Category Breakdown Tables -->
        <div class="row g-4 mb-4">

            <?php foreach (
                [
                    'expense' => $expenseCategories,
                    'income' => $incomeCategories
                ] as $type => $rows
            ):

                $sum = $type === 'expense'
                    ? $totals['expense']
                    : $totals['income'];

                $color = $type === 'expense'
                    ? 'danger'
                    : 'success';

                $headingKey = $type === 'expense'
                    ? 'expense_breakdown'
                    : 'income_breakdown';

                $emptyKey = $type === 'expense'
                    ? 'report_no_expenses'
                    : 'report_no_income';

            ?>

                <div class="col-lg-6">

                    <div class="card p-4 h-100">

                        <h2 class="h6 fw-bold mb-3">
                            <?= e(t($headingKey)) ?>
                        </h2>

                        <?php if (!$rows): ?>

                            <p class="text-muted small mb-0">
                                <?= e(t($emptyKey)) ?>
                            </p>

                        <?php endif; ?>

                        <?php foreach ($rows as $r):

                            $pct = $sum > 0
                                ? round($r['total'] / $sum * 100, 1)
                                : 0;

                        ?>

                            <div class="mb-3">

                                <div class="d-flex justify-content-between small mb-1 gap-2">

                                    <span>

                                        <i class="bi <?= e($r['icon']) ?> me-1"></i>

                                        <?= e($r['name']) ?>

                                        <span class="text-muted">
                                            (<?= (int) $r['cnt'] ?>)
                                        </span>

                                    </span>

                                    <span class="fw-semibold text-nowrap">
                                        <?= money($r['total']) ?>
                                        · <?= $pct ?>%
                                    </span>

                                </div>

                                <div
                                    class="progress"
                                    style="height: 6px">

                                    <div
                                        class="progress-bar bg-<?= $color ?>"
                                        style="width: <?= max(0, min(100, $pct)) ?>%">
                                    </div>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>


        <!-- Largest Expenses and Payment Methods -->
        <div class="row g-4">

            <!-- Top Expenses -->
            <div class="col-lg-8">

                <div class="card p-4 h-100">

                    <h2 class="h6 fw-bold mb-3">
                        <?= e(t('top_five_expenses')) ?>
                    </h2>

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

                                    <th class="text-end">
                                        <?= e(t('amount')) ?>
                                    </th>

                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($topExpenses as $transaction): ?>

                                    <tr>

                                        <td class="text-nowrap">
                                            <?= e(reportDateLabel(
                                                $transaction['transaction_date']
                                            )) ?>
                                        </td>

                                        <td>
                                            <?= e(
                                                $transaction['description'] ?: '—'
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= e($transaction['category_name']) ?>
                                        </td>

                                        <td class="text-end amount-expense">
                                            <?= money($transaction['amount']) ?>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>


            <!-- Payment Methods -->
            <div class="col-lg-4">

                <div class="card p-4 h-100">

                    <h2 class="h6 fw-bold mb-3">
                        <?= e(t('how_you_paid')) ?>
                    </h2>

                    <?php foreach ($byMethod as $method):

                        $pct = $totals['expense'] > 0
                            ? round(
                                $method['total']
                                / $totals['expense'] * 100
                            )
                            : 0;

                        $methodKey =
                            'payment_' . $method['payment_method'];

                    ?>

                        <div class="d-flex justify-content-between small mb-1 gap-2">

                            <span>
                                <?= e(t($methodKey)) ?>
                            </span>

                            <span class="fw-semibold">
                                <?= $pct ?>%
                            </span>

                        </div>

                        <div
                            class="progress mb-3"
                            style="height: 6px">

                            <div
                                class="progress-bar"
                                style="width: <?= max(0, min(100, $pct)) ?>%">
                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            </div>

        </div>


        <!-- Chart.js -->
        <script>
            const reportTrendLabels = <?= json_encode(
                $trendLabels,
                JSON_UNESCAPED_UNICODE
            ) ?>;

            const reportIncomeData = <?= json_encode(
                $chartIncome
            ) ?>;

            const reportExpenseData = <?= json_encode(
                $chartExpenses
            ) ?>;

            new Chart(document.getElementById('trendChart'), {

                type: 'line',

                data: {
                    labels: reportTrendLabels,

                    datasets: [
                        {
                            label: <?= json_encode(
                                t('income'),
                                JSON_UNESCAPED_UNICODE
                            ) ?>,

                            data: reportIncomeData,

                            borderColor: '#1f9d6b',
                            backgroundColor: 'rgba(31,157,107,.12)',
                            fill: true,
                            tension: .35
                        },

                        {
                            label: <?= json_encode(
                                t('expenses'),
                                JSON_UNESCAPED_UNICODE
                            ) ?>,

                            data: reportExpenseData,

                            borderColor: '#e05252',
                            backgroundColor: 'rgba(224,82,82,.12)',
                            fill: true,
                            tension: .35
                        }
                    ]
                },

                options: {
                    maintainAspectRatio: false,

                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });


            new Chart(document.getElementById('categoryChart'), {

                type: 'doughnut',

                data: {
                    labels: <?= json_encode(
                        $categoryLabels,
                        JSON_UNESCAPED_UNICODE
                    ) ?>,

                    datasets: [
                        {
                            data: <?= json_encode(
                                $categoryAmounts
                            ) ?>,

                            backgroundColor: chartColors()
                        }
                    ]
                },

                options: {
                    maintainAspectRatio: false,
                    cutout: '62%',

                    plugins: {
                        legend: {
                            position: 'bottom',

                            labels: {
                                boxWidth: 12
                            }
                        }
                    }
                }
            });
        </script>

    <?php endif; ?>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
