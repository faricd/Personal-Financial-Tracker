
<?php
/*
 * FinTrack - FinBot Financial Assistant
 * Rule-based assistant with English, Dari and Pashto support.
 *
 * Keep database values and calculations unchanged.
 */

require_once __DIR__ . '/language.php';

/* ---------- Shared localization helpers ---------- */

function botT(string $key, ...$values): string
{
    $translation = t('bot_' . $key);

    return $values
        ? sprintf($translation, ...$values)
        : $translation;
}

function botMonth(): string
{
    return t('month_' . date('n'));
}

function botPeriodLabel(string $period): string
{
    $keys = [
        'today' => 'period_today',
        'yesterday' => 'period_yesterday',
        'this week' => 'period_week',
        'this month' => 'period_month',
        'last month' => 'period_last_month',
        'this year' => 'period_year',
        'in total' => 'period_total'
    ];

    return isset($keys[$period])
        ? botT($keys[$period])
        : $period;
}

function botInsight(
    string $type,
    string $icon,
    string $title,
    string $description
): array {
    return [
        'type' => $type,
        'icon' => $icon,
        'title' => $title,
        'text' => $description
    ];
}

/* ---------- Part 1: Financial data ---------- */

function getMonthTotals(
    mysqli $conn,
    int $userId,
    string $month
): array {
    $row = fetchOne(
        $conn,
        "SELECT
            COALESCE(SUM(CASE WHEN type = 'income'
                THEN amount END), 0) AS income,
            COALESCE(SUM(CASE WHEN type = 'expense'
                THEN amount END), 0) AS expense
         FROM transactions
         WHERE user_id = ?
         AND DATE_FORMAT(transaction_date, '%Y-%m') = ?",
        "is",
        [$userId, $month]
    );

    return [
        'income' => (float) $row['income'],
        'expense' => (float) $row['expense']
    ];
}

function getTotalBalance(
    mysqli $conn,
    int $userId
): float {
    $row = fetchOne(
        $conn,
        "SELECT COALESCE(
            SUM(CASE WHEN type = 'income'
                THEN amount ELSE -amount END), 0
         ) AS balance
         FROM transactions
         WHERE user_id = ?",
        "i",
        [$userId]
    );

    return (float) $row['balance'];
}

function getCategorySpending(
    mysqli $conn,
    int $userId,
    string $month
): array {
    return fetchAll(
        $conn,
        "SELECT c.name, c.icon,
                SUM(t.amount) AS total
         FROM transactions t
         JOIN categories c ON c.id = t.category_id
         WHERE t.user_id = ?
           AND t.type = 'expense'
           AND DATE_FORMAT(t.transaction_date, '%Y-%m') = ?
         GROUP BY c.id, c.name, c.icon
         ORDER BY total DESC",
        "is",
        [$userId, $month]
    );
}

function getBudgetStatus(
    mysqli $conn,
    int $userId,
    string $month
): array {
    $rows = fetchAll(
        $conn,
        "SELECT b.id, b.category_id, b.amount,
                c.name, c.icon,
                COALESCE(
                    (SELECT SUM(t.amount)
                     FROM transactions t
                     WHERE t.user_id = b.user_id
                       AND t.category_id = b.category_id
                       AND t.type = 'expense'
                       AND DATE_FORMAT(
                           t.transaction_date, '%Y-%m'
                       ) = b.budget_month),
                    0
                ) AS spent
         FROM budgets b
         JOIN categories c ON c.id = b.category_id
         WHERE b.user_id = ?
           AND b.budget_month = ?
         ORDER BY c.name",
        "is",
        [$userId, $month]
    );

    foreach ($rows as &$row) {
        $row['percent'] = $row['amount'] > 0
            ? round($row['spent'] / $row['amount'] * 100)
            : 0;
    }
    unset($row);

    return $rows;
}

function getGoals(
    mysqli $conn,
    int $userId
): array {
    $rows = fetchAll(
        $conn,
        "SELECT *
         FROM savings_goals
         WHERE user_id = ?
         ORDER BY deadline IS NULL, deadline, id",
        "i",
        [$userId]
    );

    foreach ($rows as &$row) {
        $row['percent'] = $row['target_amount'] > 0
            ? min(
                100,
                round(
                    $row['saved_amount']
                    / $row['target_amount'] * 100
                )
            )
            : 0;
    }
    unset($row);

    return $rows;
}

function predictMonthEndSpending(
    float $spentSoFar
): float {
    $dayToday = (int) date('j');
    $daysInMonth = (int) date('t');

    return $spentSoFar / $dayToday * $daysInMonth;
}

/* ---------- Part 2: Financial insights ---------- */

function generateInsights(
    mysqli $conn,
    int $userId,
    string $userType
): array {
    $insights = [];

    $thisMonth = date('Y-m');
    $lastMonth = date(
        'Y-m',
        strtotime('first day of last month')
    );

    $now = getMonthTotals($conn, $userId, $thisMonth);
    $prev = getMonthTotals($conn, $userId, $lastMonth);

    // No transaction data yet
    if (
        $now['income'] == 0 &&
        $now['expense'] == 0 &&
        $prev['expense'] == 0
    ) {
        $insights[] = botInsight(
            'info',
            'bi-lightbulb',
            botT('insight_start_title'),
            botT('insight_start_text')
        );

        $insights[] = profileTip($userType);

        return $insights;
    }

    // Compare spending with last month
    if ($prev['expense'] > 0) {
        $change = (
            ($now['expense'] - $prev['expense'])
            / $prev['expense']
        ) * 100;

        if ($change > 10) {
            $insights[] = botInsight(
                'warning',
                'bi-graph-up-arrow',
                botT('insight_spending_up_title'),
                botT(
                    'insight_spending_up_text',
                    round($change)
                )
            );
        } elseif (
            $now['expense'] < $prev['expense'] &&
            date('j') > 20
        ) {
            $insights[] = botInsight(
                'success',
                'bi-graph-down-arrow',
                botT('insight_spending_down_title'),
                botT(
                    'insight_spending_down_text',
                    money($prev['expense'])
                )
            );
        }
    }

    // Largest spending category
    $categories = getCategorySpending(
        $conn,
        $userId,
        $thisMonth
    );

    if ($categories && $now['expense'] > 0) {
        $top = $categories[0];
        $share = round(
            $top['total'] / $now['expense'] * 100
        );

        $insights[] = botInsight(
            'info',
            'bi-pie-chart',
            botT('insight_top_title', $top['name']),
            botT(
                'insight_top_text',
                $top['name'],
                $share,
                money($top['total'])
            )
        );
    }

    // Budget warnings
    foreach (
        getBudgetStatus($conn, $userId, $thisMonth)
        as $budget
    ) {
        if ($budget['percent'] >= 100) {
            $insights[] = botInsight(
                'danger',
                'bi-exclamation-octagon',
                botT(
                    'insight_budget_exceeded_title',
                    $budget['name']
                ),
                botT(
                    'insight_budget_exceeded_text',
                    money($budget['spent']),
                    money($budget['amount']),
                    $budget['percent']
                )
            );
        } elseif ($budget['percent'] >= 80) {
            $insights[] = botInsight(
                'warning',
                'bi-exclamation-triangle',
                botT(
                    'insight_budget_near_title',
                    $budget['name']
                ),
                botT(
                    'insight_budget_near_text',
                    money(
                        $budget['amount'] - $budget['spent']
                    )
                )
            );
        }
    }

    // Savings rate
    if ($now['income'] > 0) {
        $rate = (
            ($now['income'] - $now['expense'])
            / $now['income']
        ) * 100;

        if ($rate >= 20) {
            $insights[] = botInsight(
                'success',
                'bi-trophy',
                botT('insight_great_saver_title'),
                botT(
                    'insight_great_saver_text',
                    round($rate)
                )
            );
        } elseif ($rate >= 0) {
            $insights[] = botInsight(
                'warning',
                'bi-piggy-bank',
                botT('insight_low_savings_title'),
                botT(
                    'insight_low_savings_text',
                    round($rate)
                )
            );
        } else {
            $insights[] = botInsight(
                'danger',
                'bi-arrow-down-circle',
                botT('insight_overspending_title'),
                botT(
                    'insight_overspending_text',
                    money(
                        $now['expense'] - $now['income']
                    )
                )
            );
        }
    }

    // Month-end forecast
    if (
        $now['expense'] > 0 &&
        date('j') < date('t')
    ) {
        $predicted = predictMonthEndSpending(
            $now['expense']
        );

        $type = (
            $now['income'] > 0 &&
            $predicted > $now['income']
        ) ? 'warning' : 'info';

        $insights[] = botInsight(
            $type,
            'bi-magic',
            botT('insight_forecast_title'),
            botT(
                'insight_forecast_text',
                money($predicted)
            )
        );
    }

    // Savings goal advice
    foreach (getGoals($conn, $userId) as $goal) {
        $remaining = $goal['target_amount']
            - $goal['saved_amount'];

        if ($remaining <= 0) {
            continue;
        }

        if ($goal['percent'] >= 80) {
            $insights[] = botInsight(
                'success',
                'bi-flag',
                botT(
                    'insight_goal_near_title',
                    $goal['title']
                ),
                botT(
                    'insight_goal_near_text',
                    money($remaining)
                )
            );
            break;
        }

        if ($goal['deadline']) {
            $monthsLeft = max(
                1,
                (int) ceil(
                    (
                        strtotime($goal['deadline']) - time()
                    ) / (30 * 86400)
                )
            );

            $insights[] = botInsight(
                'info',
                'bi-calendar-check',
                botT(
                    'insight_goal_plan_title',
                    $goal['title']
                ),
                botT(
                    'insight_goal_plan_text',
                    money($remaining / $monthsLeft)
                )
            );
            break;
        }
    }

    $insights[] = profileTip($userType);

    return $insights;
}

// Daily tip: same rotation as the original code
function profileTip(string $userType): array
{
    $prefix = $userType === 'student'
        ? 'student'
        : 'professional';

    $index = date('z') % 5 + 1;

    return botInsight(
        'primary',
        'bi-lightbulb',
        botT('tip_title'),
        botT($prefix . '_tip_' . $index)
    );
}

/* ---------- Part 3: Financial health score ---------- */

function calculateHealthScore(
    mysqli $conn,
    int $userId
): array {
    $month = getMonthTotals(
        $conn,
        $userId,
        date('Y-m')
    );

    $savingsPoints = 0;

    if ($month['income'] > 0) {
        $rate = (
            ($month['income'] - $month['expense'])
            / $month['income']
        ) * 100;

        $savingsPoints = max(
            0,
            min(40, $rate / 20 * 40)
        );
    }

    $budgets = getBudgetStatus(
        $conn,
        $userId,
        date('Y-m')
    );

    if (count($budgets) === 0) {
        $budgetPoints = 15;
    } else {
        $underLimit = count(
            array_filter(
                $budgets,
                fn($b) => $b['percent'] < 100
            )
        );

        $budgetPoints = $underLimit
            / count($budgets) * 30;
    }

    $goals = getGoals($conn, $userId);

    $goalPoints = count($goals) === 0
        ? 5
        : array_sum(
            array_column($goals, 'percent')
        ) / count($goals) / 100 * 15;

    $balancePoints = getTotalBalance(
        $conn,
        $userId
    ) > 0 ? 15 : 0;

    $score = (int) round(
        $savingsPoints +
        $budgetPoints +
        $goalPoints +
        $balancePoints
    );

    if ($score >= 80) {
        $label = botT('health_excellent');
        $color = 'success';
    } elseif ($score >= 60) {
        $label = botT('health_good');
        $color = 'primary';
    } elseif ($score >= 40) {
        $label = botT('health_fair');
        $color = 'warning';
    } else {
        $label = botT('health_attention');
        $color = 'danger';
    }

    return [
        'score' => $score,
        'label' => $label,
        'color' => $color,
        'parts' => [
            botT('health_savings') =>
                [round($savingsPoints), 40],
            botT('health_budgets') =>
                [round($budgetPoints), 30],
            botT('health_goals') =>
                [round($goalPoints), 15],
            botT('health_balance') =>
                [$balancePoints, 15]
        ]
    ];
}

/* ---------- Part 4: Question recognition ---------- */

// Translate common Dari/Pashto phrases into existing
// English intent keywords. Original question stays in storage.
function botNormalizeQuestion(string $question): string
{
    $q = mb_strtolower(trim($question));

    $phrases = [
        'ماه گذشته' => 'last month',
        'ماه قبل' => 'last month',
        'تېره میاشت' => 'last month',
        'این ماه' => 'this month',
        'همین ماه' => 'this month',
        'دا میاشت' => 'this month',
        'امروز' => 'today',
        'نن' => 'today',
        'دیروز' => 'yesterday',
        'پرون' => 'yesterday',
        'این هفته' => 'this week',
        'دا اونۍ' => 'this week',
        'امسال' => 'this year',
        'سږکال' => 'this year',
        'سال جاری' => 'this year',
        'چقدر مصرف' => 'spent',
        'څومره لګښت' => 'spent',
        'مصارف' => 'expense',
        'مصرف' => 'expense',
        'لګښتونه' => 'expense',
        'لګښت' => 'expense',
        'عاید' => 'income',
        'درآمد' => 'income',
        'بودجه' => 'budget',
        'بودیجه' => 'budget',
        'بودیجې' => 'budget',
        'پس‌انداز' => 'saving',
        'پس انداز' => 'saving',
        'سپما' => 'saving',
        'هدف' => 'goal',
        'اهداف' => 'goal',
        'موخه' => 'goal',
        'موخې' => 'goal',
        'موجودی' => 'balance',
        'بیلانس' => 'balance',
        'پاتې پیسې' => 'balance',
        'باقیمانده' => 'balance',
        'مشوره' => 'advice',
        'مشورې' => 'advice',
        'نصیحت' => 'advice',
        'پیش‌بینی' => 'forecast',
        'وړاندوینه' => 'forecast',
        'بزرگترین' => 'biggest',
        'بزرگ‌ترین' => 'biggest',
        'تر ټولو لوی' => 'biggest',
        'توان خرید' => 'afford',
        'توان پرداخت' => 'afford',
        'د اخیستلو توان' => 'afford',
        'سلام' => 'hello',
        'کمک' => 'help',
        'مرسته' => 'help'
    ];

    return str_replace(
        array_keys($phrases),
        array_values($phrases),
        $q
    );
}

function botHas(string $q, array $words): bool
{
    foreach ($words as $word) {
        if (str_contains($q, $word)) {
            return true;
        }
    }

    return false;
}

function answerQuestion(
    mysqli $conn,
    int $userId,
    string $userType,
    string $question
): string {
    $q = botNormalizeQuestion($question);

    [$from, $to, $period] = detectPeriod($q);
    $periodLabel = botPeriodLabel($period);

    $category = detectCategory(
        $conn,
        $userId,
        $q
    );

    // Greeting
    if (
        mb_strlen($q) < 25 &&
        preg_match(
            '/\b(hi|hello|hey|salam|assalamu alaikum)\b/u',
            $q
        )
    ) {
        return botT('chat_greeting');
    }

    // Help
    if (botHas($q, [
        'help',
        'what can you do',
        'what do you do'
    ])) {
        return botT('chat_help');
    }

    // Affordability
    if (
        botHas($q, ['afford', 'can i buy', 'should i buy']) &&
        preg_match('/(\d[\d,]*(\.\d+)?)/', $q, $match)
    ) {
        $price = (float) str_replace(
            ',',
            '',
            $match[1]
        );

        return answerAfford(
            $conn,
            $userId,
            $price
        );
    }

    // Financial health score
    if (botHas($q, [
        'score',
        'health',
        'how am i doing',
        'how am i'
    ])) {
        $health = calculateHealthScore(
            $conn,
            $userId
        );

        $text = botT(
            'chat_health',
            $health['score'],
            $health['label']
        ) . "\n";

        foreach (
            $health['parts'] as $name => [$got, $max]
        ) {
            $text .= "• $name: $got/$max\n";
        }

        return $text . botT('chat_health_link');
    }

    // Month-end forecast
    if (botHas($q, [
        'predict',
        'forecast',
        'end of month',
        'month end',
        'will i spend'
    ])) {
        $now = getMonthTotals(
            $conn,
            $userId,
            date('Y-m')
        );

        if ($now['expense'] == 0) {
            return botT('chat_no_forecast');
        }

        $predicted = predictMonthEndSpending(
            $now['expense']
        );

        $text = botT(
            'chat_forecast',
            money($now['expense']),
            (int) date('j'),
            money($predicted),
            botMonth()
        );

        if (
            $now['income'] > 0 &&
            $predicted > $now['income']
        ) {
            $text .= "\n" . botT(
                'chat_forecast_warning',
                money($now['income'])
            );
        }

        return $text;
    }

    // Budget status
    if (botHas($q, ['budget', 'limit'])) {
        $budgets = getBudgetStatus(
            $conn,
            $userId,
            date('Y-m')
        );

        if (!$budgets) {
            return botT('chat_no_budgets');
        }

        $text = botT(
            'chat_budgets_title',
            botMonth()
        ) . "\n";

        foreach ($budgets as $budget) {
            $status = $budget['percent'] >= 100
                ? botT('status_over')
                : (
                    $budget['percent'] >= 80
                        ? botT('status_close')
                        : botT('status_ok')
                );

            $text .= botT(
                'chat_budget_line',
                $budget['name'],
                money($budget['spent']),
                money($budget['amount']),
                $budget['percent'],
                $status
            ) . "\n";
        }

        return trim($text);
    }

    // Savings goals
    if (botHas($q, [
        'goal',
        'target',
        'saving for'
    ])) {
        $goals = getGoals($conn, $userId);

        if (!$goals) {
            return botT('chat_no_goals');
        }

        $text = botT('chat_goals_title') . "\n";

        foreach ($goals as $goal) {
            $text .= botT(
                'chat_goal_line',
                $goal['title'],
                money($goal['saved_amount']),
                money($goal['target_amount']),
                $goal['percent']
            ) . "\n";
        }

        return trim($text);
    }

    // Saving tips
    if (botHas($q, [
        'tip',
        'advice',
        'advise',
        'suggest',
        'save more',
        'saving',
        'how can i save',
        'help me save'
    ])) {
        $insights = generateInsights(
            $conn,
            $userId,
            $userType
        );

        $text = botT('chat_advice_title') . "\n";

        foreach (
            array_slice($insights, 0, 4)
            as $insight
        ) {
            $text .= '• '
                . $insight['text']
                . "\n";
        }

        return trim($text);
    }

    // Biggest expense
    if (botHas($q, [
        'biggest',
        'largest',
        'highest',
        'most',
        'top'
    ])) {
        $top = fetchOne(
            $conn,
            "SELECT c.name, SUM(t.amount) AS total
             FROM transactions t
             JOIN categories c ON c.id = t.category_id
             WHERE t.user_id = ?
               AND t.type = 'expense'
               AND t.transaction_date BETWEEN ? AND ?
             GROUP BY c.id, c.name
             ORDER BY total DESC
             LIMIT 1",
            "iss",
            [$userId, $from, $to]
        );

        $big = fetchOne(
            $conn,
            "SELECT description, amount, transaction_date
             FROM transactions
             WHERE user_id = ?
               AND type = 'expense'
               AND transaction_date BETWEEN ? AND ?
             ORDER BY amount DESC
             LIMIT 1",
            "iss",
            [$userId, $from, $to]
        );

        if (!$top) {
            return botT(
                'chat_no_expenses',
                $periodLabel
            );
        }

        $description = $big['description']
            ?: botT('no_description');

        return botT(
            'chat_biggest',
            $periodLabel,
            $top['name'],
            money($top['total']),
            money($big['amount']),
            $description,
            date(
                'd M',
                strtotime($big['transaction_date'])
            )
        );
    }

    // Income
    if (
        botHas($q, [
            'income',
            'earn',
            'earned',
            'received',
            'got paid'
        ]) ||
        ($category && $category['type'] === 'income')
    ) {
        return sumAnswer(
            $conn,
            $userId,
            'income',
            $from,
            $to,
            $periodLabel,
            $category
        );
    }

    // Expenses
    if (
        botHas($q, [
            'spend',
            'spent',
            'expense',
            'cost',
            'paid',
            'pay ',
            'spending'
        ]) ||
        $category
    ) {
        return sumAnswer(
            $conn,
            $userId,
            'expense',
            $from,
            $to,
            $periodLabel,
            $category
        );
    }

    // Balance
    if (botHas($q, [
        'balance',
        'left',
        'how much do i have',
        'money do i have',
        'remaining'
    ])) {
        $now = getMonthTotals(
            $conn,
            $userId,
            date('Y-m')
        );

        return botT(
            'chat_balance',
            money(getTotalBalance($conn, $userId)),
            money($now['income']),
            money($now['expense']),
            money(
                $now['income'] - $now['expense']
            )
        );
    }

    return botT('chat_unknown');
}

/* ---------- Part 5: Question helpers ---------- */

function detectPeriod(string $q): array
{
    $today = date('Y-m-d');

    if (str_contains($q, 'today')) {
        return [$today, $today, 'today'];
    }

    if (str_contains($q, 'yesterday')) {
        $yesterday = date(
            'Y-m-d',
            strtotime('-1 day')
        );

        return [
            $yesterday,
            $yesterday,
            'yesterday'
        ];
    }

    if (str_contains($q, 'week')) {
        return [
            date(
                'Y-m-d',
                strtotime('monday this week')
            ),
            $today,
            'this week'
        ];
    }

    if (
        str_contains($q, 'last month') ||
        str_contains($q, 'previous month')
    ) {
        return [
            date(
                'Y-m-01',
                strtotime('first day of last month')
            ),
            date(
                'Y-m-t',
                strtotime('first day of last month')
            ),
            'last month'
        ];
    }

    if (str_contains($q, 'year')) {
        return [
            date('Y-01-01'),
            $today,
            'this year'
        ];
    }

    if (
        str_contains($q, 'all time') ||
        str_contains($q, 'total')
    ) {
        return [
            '2000-01-01',
            $today,
            'in total'
        ];
    }

    return [
        date('Y-m-01'),
        $today,
        'this month'
    ];
}

// Match user-created category names as stored in MySQL
function detectCategory(
    mysqli $conn,
    int $userId,
    string $q
): ?array {
    $categories = fetchAll(
        $conn,
        "SELECT id, name, type
         FROM categories
         WHERE user_id = ?",
        "i",
        [$userId]
    );

    foreach ($categories as $category) {
        $name = mb_strtolower($category['name']);
        $firstWord = strtok($name, ' &');

        $matchesName = str_contains($q, $name);

        $matchesFirst =
            mb_strlen($firstWord) >= 4 &&
            preg_match(
                '/\b' . preg_quote($firstWord, '/') . '/u',
                $q
            );

        if ($matchesName || $matchesFirst) {
            return $category;
        }
    }

    return null;
}

function sumAnswer(
    mysqli $conn,
    int $userId,
    string $type,
    string $from,
    string $to,
    string $label,
    ?array $category
): string {
    $sql = "SELECT
                COALESCE(SUM(amount), 0) AS total,
                COUNT(*) AS cnt
            FROM transactions
            WHERE user_id = ?
              AND type = ?
              AND transaction_date BETWEEN ? AND ?";

    $types = "isss";
    $params = [$userId, $type, $from, $to];

    if ($category) {
        $sql .= " AND category_id = ?";
        $types .= "i";
        $params[] = $category['id'];
    }

    $row = fetchOne(
        $conn,
        $sql,
        $types,
        $params
    );

    $categoryName = $category
        ? $category['name']
        : botT('all_categories');

    if ($row['cnt'] == 0) {
        return botT(
            'chat_sum_empty',
            $type === 'income'
                ? t('income')
                : t('expenses'),
            $categoryName,
            $label
        );
    }

    return botT(
        'chat_sum',
        money($row['total']),
        $type === 'income'
            ? t('income')
            : t('expenses'),
        $categoryName,
        $label,
        $row['cnt']
    );
}

function answerAfford(
    mysqli $conn,
    int $userId,
    float $price
): string {
    $balance = getTotalBalance(
        $conn,
        $userId
    );

    $now = getMonthTotals(
        $conn,
        $userId,
        date('Y-m')
    );

    $leftThisMonth =
        $now['income'] - $now['expense'];

    if ($price <= 0) {
        return botT('afford_invalid');
    }

    if ($price > $balance) {
        return botT(
            'afford_no',
            money($price),
            money($balance)
        );
    }

    if ($price > $leftThisMonth) {
        return botT(
            'afford_savings',
            money(max(0, $leftThisMonth))
        );
    }

    $share = round(
        $price / $leftThisMonth * 100
    );

    if ($share <= 30) {
        return botT(
            'afford_yes',
            $share,
            money($leftThisMonth)
        );
    }

    return botT(
        'afford_caution',
        $share,
        money($leftThisMonth)
    );
}
