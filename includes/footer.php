
<?php
/*
 * FinTrack - Shared Footer
 * English, Dari, and Pashto support.
 */

require_once __DIR__ . '/language.php';
?>

</main>

<?php if (isset($_SESSION['user_id'])): ?>

<!-- Website Footer -->
<footer class="app-footer py-4 mt-5">

    <div class="container d-flex flex-column flex-md-row justify-content-between gap-2 small">

        <span>
            <i class="bi bi-wallet2 me-1"></i>
            <b>FinTrack</b>
            &middot;
            <?= e(t('personal_financial_tracker')) ?>
        </span>

        <span class="text-muted">
            <?= e(t('built_with')) ?>
        </span>

    </div>

</footer>

<?php endif; ?>


<?php if (isset($_SESSION['user_id'])): ?>

<!-- Delete Confirmation Modal -->
<div class="delete-modal" id="deleteModal">

    <div class="delete-modal-box">

        <div class="delete-icon">
            <i class="bi bi-trash3"></i>
        </div>

        <h3 id="deleteModalTitle">
            <?= e(t('delete_item_confirm')) ?>
        </h3>

        <p>
            <?= e(t('delete_warning')) ?>
        </p>

        <div class="delete-modal-actions">

            <button
                type="button"
                class="btn btn-light"
                id="cancelDelete">

                <?= e(t('cancel')) ?>

            </button>

            <button
                type="button"
                class="btn btn-danger"
                id="confirmDelete">

                <i class="bi bi-trash3 me-1"></i>
                <?= e(t('delete')) ?>

            </button>

        </div>

    </div>

</div>


<!-- Floating FinBot Chat -->
<?php if (($activePage ?? '') !== 'assistant'): ?>

    <!-- Chat Toggle Button -->
    <button
        type="button"
        class="chat-toggle"
        id="chatToggle"
        title="<?= e(t('ask_finbot')) ?>"
        aria-label="<?= e(t('ask_finbot')) ?>">

        <i class="bi bi-robot"></i>

    </button>


    <!-- Chat Panel -->
    <div class="chat-panel" id="chatPanel">

        <!-- Chat Header -->
        <div class="chat-header">

            <div class="d-flex align-items-center gap-2">

                <span class="bot-avatar">
                    <i class="bi bi-robot"></i>
                </span>

                <div>

                    <div class="fw-bold">
                        FinBot
                    </div>

                    <div class="small opacity-75">
                        <?= e(t('your_money_assistant')) ?>
                    </div>

                </div>

            </div>

            <!-- Close Chat -->
            <button
                type="button"
                class="btn btn-sm text-white"
                id="chatClose"
                aria-label="<?= e(t('close_chat')) ?>">

                <i class="bi bi-x-lg"></i>

            </button>

        </div>


        <!-- Chat Messages -->
        <div class="chat-messages" id="chatMessages">

            <div class="chat-msg bot">

                <?= e(t('hello')) ?>
                <?= e(explode(' ', $_SESSION['user_name'])[0]) ?>! 👋

                <?= e(t('finbot_greeting')) ?>

            </div>

        </div>


        <!-- Suggested Questions -->
        <div class="chat-suggestions">

            <button
                type="button"
                class="chip"
                data-question="How much did I spend this month?">

                <?= e(t('spent_this_month_question')) ?>

            </button>

            <button
                type="button"
                class="chip"
                data-question="Give me saving tips">

                <?= e(t('saving_tips_question')) ?>

            </button>

            <button
                type="button"
                class="chip"
                data-question="How are my budgets?">

                <?= e(t('my_budgets_question')) ?>

            </button>

        </div>


        <!-- Chat Form -->
        <form class="chat-form" id="chatForm">

            <input
                type="text"
                class="form-control"
                id="chatInput"
                placeholder="<?= e(t('ask_finbot_placeholder')) ?>"
                aria-label="<?= e(t('ask_finbot')) ?>"
                maxlength="300"
                autocomplete="off"
                required>

            <button
                type="submit"
                class="btn btn-primary"
                aria-label="<?= e(t('send_message')) ?>">

                <i class="bi bi-send"></i>

            </button>

        </form>

    </div>

<?php endif; ?>

<?php endif; ?>


<!-- Bootstrap JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>


<script>
    window.fintrackTranslations = <?= json_encode([
        'password_strength' => t('password_strength'),
        'too_short' => t('too_short'),
        'weak' => t('weak'),
        'fair' => t('fair'),
        'good' => t('good'),
        'strong' => t('strong'),
        'very_strong' => t('very_strong'),
        'passwords_do_not_match' => t('passwords_do_not_match'),
        'date_range_invalid' => t('date_range_invalid'),
        'delete_item_confirm' => t('delete_item_confirm'),
        'finbot_thinking' => t('finbot_thinking'),
        'finbot_error' => t('finbot_error'),
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>

<!-- FinTrack JavaScript -->
<script src="assets/js/script.js"></script>


</body>
</html>
