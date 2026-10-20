
<?php
/*
 * FinTrack Transaction Functions
 * Supports English, Dari, and Pashto.
 *
 * Used by:
 * - transactions.php
 * - add_transaction.php
 * - edit_transaction.php
 * - ajax/search_transactions.php
 * - export_csv.php
 */

require_once __DIR__ . '/language.php';


// Read transaction filters
function readTransactionFilters(array $input): array
{
    $filters = [
        'search'   => trim($input['search'] ?? ''),
        'type'     => $input['type'] ?? '',
        'category' => (int) ($input['category'] ?? 0),
        'method'   => $input['method'] ?? '',
        'from'     => $input['from'] ?? '',
        'to'       => $input['to'] ?? '',
    ];

    if (!in_array($filters['type'], ['income', 'expense'], true)) {
        $filters['type'] = '';
    }

    if (!array_key_exists($filters['method'], paymentMethods())) {
        $filters['method'] = '';
    }

    if ($filters['from'] !== '' && !isValidDate($filters['from'])) {
        $filters['from'] = '';
    }

    if ($filters['to'] !== '' && !isValidDate($filters['to'])) {
        $filters['to'] = '';
    }

    return $filters;
}


// Build transaction search query
function buildTransactionQuery(int $userId, array $filters): array
{
    $sql = "SELECT t.*, c.name AS category_name, c.icon
            FROM transactions t
            JOIN categories c ON c.id = t.category_id
            WHERE t.user_id = ?";

    $types = "i";
    $params = [$userId];

    if ($filters['search'] !== '') {

        $sql .= " AND (t.description LIKE ? OR c.name LIKE ?)";

        $like = "%" . $filters['search'] . "%";
        $types .= "ss";

        array_push($params, $like, $like);
    }

    if ($filters['type'] !== '') {

        $sql .= " AND t.type = ?";
        $types .= "s";
        $params[] = $filters['type'];
    }

    if ($filters['category'] > 0) {

        $sql .= " AND t.category_id = ?";
        $types .= "i";
        $params[] = $filters['category'];
    }

    if ($filters['method'] !== '') {

        $sql .= " AND t.payment_method = ?";
        $types .= "s";
        $params[] = $filters['method'];
    }

    if ($filters['from'] !== '') {

        $sql .= " AND t.transaction_date >= ?";
        $types .= "s";
        $params[] = $filters['from'];
    }

    if ($filters['to'] !== '') {

        $sql .= " AND t.transaction_date <= ?";
        $types .= "s";
        $params[] = $filters['to'];
    }

    $sql .= " ORDER BY t.transaction_date DESC, t.id DESC";

    return [$sql, $types, $params];
}


// Render transaction table rows
function renderTransactionRows(array $rows): void
{
    if (!$rows) {
        ?>
        <tr>
            <td colspan="6">
                <div class="empty-state">
                    <i class="bi bi-search"></i>

                    <p>
                        <?= e(t('no_transactions_found')) ?>
                    </p>
                </div>
            </td>
        </tr>
        <?php
        return;
    }

    foreach ($rows as $row) {

        $isIncome = $row['type'] === 'income';

        $paymentKey = 'payment_' . $row['payment_method'];

        $isRTL = in_array(
            $_SESSION['lang'] ?? 'en',
            ['fa', 'ps'],
            true
        );
        ?>

        <tr>

            <!-- Date -->
            <td class="text-nowrap">
                <?= date('d M Y', strtotime($row['transaction_date'])) ?>
            </td>

            <!-- Description -->
            <td>
                <?= e($row['description'] ?: '—') ?>
            </td>

            <!-- Category -->
            <td>
                <span class="cat-pill">

                    <i class="bi <?= e($row['icon']) ?>"></i>

                    <?= e($row['category_name']) ?>

                </span>
            </td>

            <!-- Payment Method -->
            <td class="small text-muted">
                <?= e(t($paymentKey)) ?>
            </td>

            <!-- Amount -->
            <td class="text-nowrap <?= $isIncome ? 'amount-income' : 'amount-expense' ?>">
                <?= $isIncome ? '+' : '-' ?>
                <?= money($row['amount']) ?>
            </td>

            <!-- Actions -->
            <td class="text-end text-nowrap">

                <!-- Edit -->
                <a
                    href="edit_transaction.php?id=<?= (int) $row['id'] ?>"
                    class="btn btn-sm btn-outline-primary"
                    title="<?= e(t('edit')) ?>"
                    aria-label="<?= e(t('edit')) ?>">

                    <i class="bi bi-pencil"></i>

                </a>

                <!-- Delete -->
                <form
                    method="POST"
                    action="delete_transaction.php"
                    class="d-inline delete-form">

                    <?= csrfField() ?>

                    <input
                        type="hidden"
                        name="id"
                        value="<?= (int) $row['id'] ?>">

                    <button
                        type="button"
                        class="btn btn-sm btn-outline-danger delete-btn"
                        data-title="<?= e(t('delete_transaction_confirm')) ?>"
                        title="<?= e(t('delete')) ?>"
                        aria-label="<?= e(t('delete')) ?>">

                        <i class="bi bi-trash"></i>

                    </button>

                </form>

            </td>

        </tr>

        <?php
    }
}


// Validate Add/Edit Transaction forms
function validateTransaction(
    mysqli $conn,
    int $userId,
    array $input
): array {

    $data = [
        'type' => $input['type'] ?? '',

        'category_id' => (int) ($input['category_id'] ?? 0),

        'amount' => (float) ($input['amount'] ?? 0),

        'payment_method' => $input['payment_method'] ?? 'cash',

        'description' => trim($input['description'] ?? ''),

        'transaction_date' => $input['transaction_date'] ?? '',
    ];

    // Transaction type
    if (!in_array($data['type'], ['income', 'expense'], true)) {

        return [
            $data,
            t('validation_transaction_type')
        ];
    }

    // Amount
    if (
        $data['amount'] <= 0 ||
        $data['amount'] > 9999999999
    ) {

        return [
            $data,
            t('validation_positive_amount')
        ];
    }

    // Payment method
    if (!array_key_exists($data['payment_method'], paymentMethods())) {

        return [
            $data,
            t('validation_payment_method')
        ];
    }

    // Date
    if (!isValidDate($data['transaction_date'])) {

        return [
            $data,
            t('validation_valid_date')
        ];
    }

    // Future dates are not allowed
    if ($data['transaction_date'] > date('Y-m-d')) {

        return [
            $data,
            t('validation_future_date')
        ];
    }

    // Description length
    if (mb_strlen($data['description']) > 255) {

        return [
            $data,
            t('validation_description_length')
        ];
    }

    // Ensure category belongs to this user
    // and matches the transaction type
    $category = fetchOne(
        $conn,
        "SELECT id
         FROM categories
         WHERE id = ?
         AND user_id = ?
         AND type = ?",
        "iis",
        [
            $data['category_id'],
            $userId,
            $data['type']
        ]
    );

    if (!$category) {

        return [
            $data,
            t('validation_category_type')
        ];
    }

    return [$data, ''];
}
