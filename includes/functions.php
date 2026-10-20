<?php
/*
 * Helper functions used on many pages.
 * Keeping them here means we do not repeat the same code in every file.
 */

/* ---------- Output helpers ---------- */

// Escape text before printing it in HTML (prevents XSS attacks)
function e($text): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

// Format a number as money using the user's currency, e.g. ৳1,250.00
function money($amount): string
{
    $symbol = $_SESSION['currency'] ?? '$';
    $sign   = $amount < 0 ? '-' : '';

    // "\u{200E}" is an invisible left-to-right mark. Without it, right-to-left
    // symbols like the Afghani would appear after the number instead of before.
    return "\u{200E}" . $sign . $symbol . "\u{200E}" . number_format(abs((float) $amount), 2);
}

// Hidden input that carries the CSRF security token inside every POST form
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e($_SESSION['csrf_token']) . '">';
}

/* ---------- Flash messages (shown once after a redirect) ---------- */

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function showFlash(): void
{
    if (empty($_SESSION['flash'])) {
        return;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    $icon = $flash['type'] === 'success' ? 'bi-check-circle' : 'bi-exclamation-triangle';
    echo '<div class="container mt-3">
            <div class="alert alert-' . e($flash['type']) . ' alert-dismissible fade show auto-hide" role="alert">
                <i class="bi ' . $icon . ' me-2"></i>' . e($flash['message']) . '
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
          </div>';
}

/* ---------- Database helpers (all use prepared statements) ---------- */

// Run a prepared query and return all rows as an array
function fetchAll(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Run a prepared query and return only the first row (or null)
function fetchOne(mysqli $conn, string $sql, string $types = '', array $params = []): ?array
{
    $rows = fetchAll($conn, $sql, $types, $params);
    return $rows[0] ?? null;
}

/* ---------- Validation helpers ---------- */

// Check that a string is a real date in YYYY-MM-DD format
function isValidDate(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

// Check that a string is a real month in YYYY-MM format
function isValidMonth(string $month): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $month . '-01');
    return $d && $d->format('Y-m') === $month;
}

/* ---------- App data ---------- */

// Payment methods shown in forms (database value => label)
function paymentMethods(): array
{
    return [
        'cash'           => 'Cash',
        'card'           => 'Card',
        'mobile_banking' => 'Mobile Banking (bKash/Nagad)',
        'bank'           => 'Bank Transfer',
    ];
}

// Currencies a user can choose in their profile
function currencies(): array
{
        return ['$' => '$ Dollar (USD)', '৳' => '৳ Taka (BDT)', '€' => '€ Euro (EUR)', '£' => '£ Pound (GBP)', '₹' => '₹ Rupee (INR)', '؋' => '؋ Afghani (AFN)'];
}

// Give every new user a ready-made set of categories based on their profile
function createDefaultCategories(mysqli $conn, int $userId, string $userType): void
{
    if ($userType === 'student') {
        $list = [
            ['Allowance', 'income', 'bi-cash-coin'],
            ['Part-time Job', 'income', 'bi-briefcase'],
            ['Scholarship', 'income', 'bi-award'],
            ['Food & Dining', 'expense', 'bi-cup-hot'],
            ['Transport', 'expense', 'bi-bus-front'],
            ['Books & Supplies', 'expense', 'bi-book'],
            ['Tuition Fees', 'expense', 'bi-mortarboard'],
            ['Mobile & Internet', 'expense', 'bi-phone'],
            ['Entertainment', 'expense', 'bi-controller'],
            ['Rent & Hostel', 'expense', 'bi-house'],
        ];
    } else {
        $list = [
            ['Salary', 'income', 'bi-briefcase'],
            ['Bonus', 'income', 'bi-gift'],
            ['Freelance', 'income', 'bi-laptop'],
            ['Investment', 'income', 'bi-graph-up-arrow'],
            ['Food & Groceries', 'expense', 'bi-basket'],
            ['Rent', 'expense', 'bi-house'],
            ['Utilities', 'expense', 'bi-lightning'],
            ['Transport', 'expense', 'bi-car-front'],
            ['Health', 'expense', 'bi-heart-pulse'],
            ['Shopping', 'expense', 'bi-bag'],
            ['Entertainment', 'expense', 'bi-controller'],
        ];
    }

    $stmt = $conn->prepare("INSERT INTO categories (user_id, name, type, icon) VALUES (?, ?, ?, ?)");
    foreach ($list as $category) {
        [$name, $type, $icon] = $category;
        $stmt->bind_param("isss", $userId, $name, $type, $icon);
        $stmt->execute();
    }
}

// Icons the user can pick from on the Categories page
function categoryIcons(): array
{
    return [
        'bi-tag', 'bi-cash-coin', 'bi-briefcase', 'bi-award', 'bi-laptop', 'bi-gift', 'bi-graph-up-arrow',
        'bi-cup-hot', 'bi-basket', 'bi-bus-front', 'bi-car-front', 'bi-book', 'bi-mortarboard', 'bi-phone',
        'bi-controller', 'bi-bag', 'bi-house', 'bi-lightning', 'bi-heart-pulse', 'bi-airplane', 'bi-people',
    ];
}

// Check the category form. $ignoreId is used when editing (so it does not clash with itself)

function validateCategory(
    mysqli $conn,
    int $userId,
    array $input,
    int $ignoreId = 0
): array {

    // Load translation system
    require_once __DIR__ . '/language.php';

    $data = [
        'name' => trim($input['name'] ?? ''),
        'type' => $input['type'] ?? '',
        'icon' => $input['icon'] ?? 'bi-tag',
    ];

    // Validate category name
    if (
        mb_strlen($data['name']) < 2 ||
        mb_strlen($data['name']) > 50
    ) {
        return [
            $data,
            t('validation_category_name')
        ];
    }

    // Validate category type
    if (!in_array($data['type'], ['income', 'expense'], true)) {
        return [
            $data,
            t('validation_transaction_type')
        ];
    }

    // Validate icon
    if (!in_array($data['icon'], categoryIcons(), true)) {
        $data['icon'] = 'bi-tag';
    }

    // Check duplicate category
    $duplicate = fetchOne(
        $conn,
        "SELECT id
         FROM categories
         WHERE user_id = ?
         AND name = ?
         AND type = ?
         AND id <> ?",
        "issi",
        [
            $userId,
            $data['name'],
            $data['type'],
            $ignoreId
        ]
    );

    if ($duplicate) {

        $typeLabel = $data['type'] === 'income'
            ? t('income')
            : t('expenses');

        $message = sprintf(
            t('validation_duplicate_category'),
            $typeLabel,
            $data['name']
        );

        return [$data, $message];
    }

    return [$data, ''];
}


// Check the savings goal form. Returns [data, errorMessage]

/*
 * Validate the savings goal form.
 * Returns [data, errorMessage].
 * Supports English, Dari, and Pashto.
 */
function validateGoal(array $input, bool $isNew = true): array
{
    // Load the translation system
    require_once __DIR__ . '/language.php';

    $data = [
        'title'         => trim($input['title'] ?? ''),
        'target_amount' => (float) ($input['target_amount'] ?? 0),
        'saved_amount'  => (float) ($input['saved_amount'] ?? 0),
        'deadline'      => trim($input['deadline'] ?? ''),
    ];

    // Validate goal title
    if (
        mb_strlen($data['title']) < 2 ||
        mb_strlen($data['title']) > 100
    ) {
        return [
            $data,
            t('validation_goal_title')
        ];
    }

    // Validate target amount
    if ($data['target_amount'] <= 0) {
        return [
            $data,
            t('validation_goal_target')
        ];
    }

    // Validate saved amount
    if ($data['saved_amount'] < 0) {
        return [
            $data,
            t('validation_goal_saved')
        ];
    }

    // Validate deadline
    if ($data['deadline'] === '') {

        // No deadline: store NULL in database
        $data['deadline'] = null;

    } elseif (!isValidDate($data['deadline'])) {

        return [
            $data,
            t('validation_goal_deadline')
        ];

    } elseif (
        $isNew &&
        $data['deadline'] < date('Y-m-d')
    ) {

        return [
            $data,
            t('validation_goal_past_deadline')
        ];
    }

    return [$data, ''];
}

