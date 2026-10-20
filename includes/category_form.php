
<?php
/*
 * FinTrack - Shared Category Form
 * Used by:
 * - categories.php
 * - edit_category.php
 */

require_once __DIR__ . '/language.php';

/**
 * Variables supplied by the parent page.
 *
 * @var array $data
 * @var string $submitLabel
 */
?>

<form method="POST"
      class="needs-validation"
      novalidate>

    <?= csrfField() ?>

    <!-- Category Name -->
    <div class="mb-3">

        <label class="form-label" for="categoryName">
            <?= e(t('category_name')) ?>
        </label>

        <input
            type="text"
            name="name"
            id="categoryName"
            list="categorySuggestions"
            class="form-control"
            value="<?= e($data['name']) ?>"
            placeholder="<?= e(t('category_name_example')) ?>"
            required
            minlength="2"
            maxlength="50">

        <!-- Category suggestions -->
        <datalist id="categorySuggestions">

            <!-- Income categories -->
            <option value="Salary">
            <option value="Allowance">
            <option value="Freelance">
            <option value="Scholarship">
            <option value="Business">
            <option value="Gift">

            <!-- Expense categories -->
            <option value="Food & Dining">
            <option value="Groceries">
            <option value="Transport">
            <option value="Rent">
            <option value="Utilities">
            <option value="Healthcare">
            <option value="Education">
            <option value="Subscriptions">
            <option value="Travel">
            <option value="Gifts & Donations">

        </datalist>

        <div class="invalid-feedback">
            <?= e(t('category_name_validation')) ?>
        </div>

    </div>


    <!-- Category Type -->
    <div class="mb-3">

        <label class="form-label">
            <?= e(t('type')) ?>
        </label>

        <div class="type-switch">

            <!-- Income -->
            <input
                type="radio"
                class="btn-check"
                name="type"
                id="catIncome"
                value="income"
                <?= $data['type'] === 'income' ? 'checked' : '' ?>
                required>

            <label
                class="btn btn-outline-success"
                for="catIncome">

                <?= e(t('income')) ?>

            </label>

            <!-- Expense -->
            <input
                type="radio"
                class="btn-check"
                name="type"
                id="catExpense"
                value="expense"
                <?= $data['type'] === 'expense' ? 'checked' : '' ?>>

            <label
                class="btn btn-outline-danger"
                for="catExpense">

                <?= e(t('expenses')) ?>

            </label>

        </div>

    </div>


    <!-- Category Icon -->
    <div class="mb-4">

        <label class="form-label">
            <?= e(t('category_icon')) ?>
        </label>

        <div class="icon-picker">

            <?php foreach (categoryIcons() as $icon): ?>

                <input
                    type="radio"
                    class="btn-check"
                    name="icon"
                    id="icon-<?= e($icon) ?>"
                    value="<?= e($icon) ?>"
                    <?= $data['icon'] === $icon ? 'checked' : '' ?>>

                <label
                    class="btn btn-outline-secondary"
                    for="icon-<?= e($icon) ?>"
                    title="<?= e(t('select_icon')) ?>">

                    <i class="bi <?= e($icon) ?>"></i>

                </label>

            <?php endforeach; ?>

        </div>

    </div>


    <!-- Submit -->
    <button class="btn btn-primary w-100" type="submit">
        <?= $submitLabel ?>
    </button>

</form>
