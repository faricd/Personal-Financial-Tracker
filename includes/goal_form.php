
<?php
/*
 * FinTrack - Shared Savings Goal Form
 * Supports English, Dari, and Pashto.
 *
 * Used by:
 * - goals.php
 * - edit_goal.php
 */

require_once __DIR__ . '/language.php';

/**
 * Variables provided by the parent page.
 *
 * @var array $form
 * @var string $submitLabel
 * @var string $action
 */
?>

<form method="POST"
      class="needs-validation"
      novalidate>

    <?= csrfField() ?>

    <input
        type="hidden"
        name="action"
        value="<?= e($action) ?>">

    <!-- Goal Title -->
    <div class="mb-3">

        <label for="goalTitle" class="form-label">
            <?= e(t('goal_title')) ?>
        </label>

        <input
            type="text"
            name="title"
            id="goalTitle"
            class="form-control"
            value="<?= e($form['title'] ?? '') ?>"
            placeholder="<?= e(t('goal_title_example')) ?>"
            maxlength="100"
            minlength="2"
            required>

        <div class="invalid-feedback">
            <?= e(t('goal_title_validation')) ?>
        </div>

    </div>


    <!-- Target Amount -->
    <div class="mb-3">

        <label for="targetAmount" class="form-label">
            <?= e(t('target_amount')) ?>
        </label>

        <div class="input-group">

            <span class="input-group-text">
                <?= e($_SESSION['currency'] ?? 'USD') ?>
            </span>

            <input
                type="number"
                name="target_amount"
                id="targetAmount"
                class="form-control"
                min="0.01"
                step="0.01"
                value="<?= e($form['target_amount'] ?? '') ?>"
                placeholder="1000.00"
                required>

        </div>

        <div class="form-text">
            <?= e(t('target_amount_help')) ?>
        </div>

    </div>


    <!-- Already Saved Amount -->
    <div class="mb-3">

        <label for="savedAmount" class="form-label">
            <?= e(t('already_saved')) ?>
        </label>

        <div class="input-group">

            <span class="input-group-text">
                <?= e($_SESSION['currency'] ?? 'USD') ?>
            </span>

            <input
                type="number"
                name="saved_amount"
                id="savedAmount"
                class="form-control"
                min="0"
                step="0.01"
                value="<?= e($form['saved_amount'] ?? '0') ?>"
                placeholder="0.00">

        </div>

        <div class="form-text">
            <?= e(t('already_saved_help')) ?>
        </div>

    </div>


    <!-- Goal Deadline -->
    <div class="mb-4">

        <label for="goalDeadline" class="form-label">
            <?= e(t('target_date')) ?>
            <span class="text-muted">
                (<?= e(t('optional')) ?>)
            </span>
        </label>

        <input
            type="date"
            name="deadline"
            id="goalDeadline"
            class="form-control"
            value="<?= e($form['deadline'] ?? '') ?>">

        <div class="form-text">
            <?= e(t('goal_deadline_help')) ?>
        </div>

    </div>


    <!-- Submit -->
    <button
        type="submit"
        class="btn btn-primary w-100">

        <?= $submitLabel ?>

    </button>

</form>
