

<?php
/*
 * Shared Transaction Form
 * Used by add_transaction.php and edit_transaction.php
 */

require_once __DIR__ . '/language.php';

/**
 * Variables provided by the parent page.
 *
 * @var array $data
 * @var array $categories
 * @var string $submitLabel
 */
?>



<form method="POST"
      class="needs-validation"
      id="transactionForm"
      novalidate>

    <?= csrfField() ?>

    <div class="row g-3">

        <!-- Income / Expense -->
        <div class="col-12">

            <label class="form-label">
                <?= e(t('type')) ?>
            </label>

            <div class="type-switch">

                <input
                    type="radio"
                    class="btn-check"
                    name="type"
                    id="typeIncome"
                    value="income"
                    <?= $data['type'] === 'income' ? 'checked' : '' ?>
                    required>

                <label class="btn btn-outline-success" for="typeIncome">
                    <i class="bi bi-arrow-down-left me-1"></i>
                    <?= e(t('income')) ?>
                </label>

                <input
                    type="radio"
                    class="btn-check"
                    name="type"
                    id="typeExpense"
                    value="expense"
                    <?= $data['type'] === 'expense' ? 'checked' : '' ?>>

                <label class="btn btn-outline-danger" for="typeExpense">
                    <i class="bi bi-arrow-up-right me-1"></i>
                    <?= e(t('expenses')) ?>
                </label>

            </div>
        </div>

        <!-- Category -->
        <div class="col-md-6">

            <label class="form-label" for="category_id">
                <?= e(t('category')) ?>
            </label>

            <select
                name="category_id"
                id="category_id"
                class="form-select"
                required>

                <option value="">
                    <?= e(t('choose_category')) ?>
                </option>

                <?php foreach ($categories as $c): ?>
                    <option
                        value="<?= (int) $c['id'] ?>"
                        data-type="<?= e($c['type']) ?>"
                        <?= (int) $data['category_id'] === (int) $c['id'] ? 'selected' : '' ?>>

                        <?= e($c['name']) ?>

                    </option>
                <?php endforeach; ?>

            </select>

            <div class="invalid-feedback">
                <?= e(t('category_required')) ?>
            </div>

            <div class="form-text">
                <a href="categories.php">
                    + <?= e(t('add_new_category')) ?>
                </a>
            </div>

        </div>

        <!-- Amount -->
        <div class="col-md-6">

            <label class="form-label" for="amount">
                <?= e(t('amount')) ?>
                (<?= e($_SESSION['currency']) ?>)
            </label>

            <input
                type="number"
                name="amount"
                id="amount"
                min="0.01"
                step="0.01"
                max="9999999999"
                value="<?= $data['amount'] !== '' ? e($data['amount']) : '' ?>"
                class="form-control"
                placeholder="0.00"
                required>

            <div class="invalid-feedback">
                <?= e(t('amount_required')) ?>
            </div>

        </div>

        <!-- Date -->
        <div class="col-md-6">

            <label class="form-label" for="transaction_date">
                <?= e(t('date')) ?>
            </label>

            <input
                type="date"
                name="transaction_date"
                id="transaction_date"
                max="<?= date('Y-m-d') ?>"
                value="<?= e($data['transaction_date']) ?>"
                class="form-control"
                required>

            <div class="invalid-feedback">
                <?= e(t('valid_date_required')) ?>
            </div>

        </div>

        <!-- Payment Method -->
        <div class="col-md-6">

            <label class="form-label" for="payment_method">
                <?= e(t('payment_method')) ?>
            </label>

            <select
                name="payment_method"
                id="payment_method"
                class="form-select"
                required>

                <?php foreach (paymentMethods() as $value => $label): ?>
                    <option
                        value="<?= e($value) ?>"
                        <?= $data['payment_method'] === $value ? 'selected' : '' ?>>

                        <?= e(t('payment_' . $value)) ?>

                    </option>
                <?php endforeach; ?>

            </select>

        </div>

        <!-- Description -->
        <div class="col-12">

            <label class="form-label" for="description">
                <?= e(t('description')) ?>

                <span class="text-muted">
                    (<?= e(t('optional')) ?>)
                </span>
            </label>

            <textarea
                name="description"
                id="description"
                class="form-control"
                rows="3"
                maxlength="255"
                placeholder="<?= e(t('description_example')) ?>"><?= e($data['description']) ?></textarea>

            <div class="form-text text-end">
                <span id="charCount">0</span>/255
            </div>

        </div>

    </div>

    <!-- Form Buttons -->
    <div class="mt-4 d-flex gap-2">

        <button class="btn btn-primary px-4">
            <i class="bi bi-check2 me-1"></i>
            <?= $submitLabel ?>
        </button>

        <a href="transactions.php" class="btn btn-light">
            <?= e(t('cancel')) ?>
        </a>

    </div>

</form>
