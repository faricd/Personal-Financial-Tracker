
<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ai_helper.php';
require_once __DIR__ . '/../includes/language.php';

requireLogin();

$userId = (int) $_SESSION["user_id"];

// Calculate financial insights and health score
$insights = generateInsights(
    $conn,
    $userId,
    $_SESSION['user_type']
);

$health = calculateHealthScore($conn, $userId);

// Load the latest 10 chat messages, oldest first
$history = array_reverse(
    fetchAll(
        $conn,
        "SELECT question, answer
         FROM ai_chat_history
         WHERE user_id = ?
         ORDER BY id DESC
         LIMIT 10",
        "i",
        [$userId]
    )
);

// Keep original English questions for the FinBot backend.
// Translate only the text displayed on each button.
$suggestions = [
    [
        'question' => 'How much did I spend this month?',
        'label' => 'assistant_question_spending'
    ],
    [
        'question' => 'Spent on food this week',
        'label' => 'assistant_question_food'
    ],
    [
        'question' => 'Can I afford 3000?',
        'label' => 'assistant_question_afford'
    ],
    [
        'question' => 'How are my budgets?',
        'label' => 'assistant_question_budgets'
    ],
    [
        'question' => 'Forecast my month',
        'label' => 'assistant_question_forecast'
    ],
    [
        'question' => 'Give me saving tips',
        'label' => 'assistant_question_saving'
    ],
    [
        'question' => 'What is my biggest expense?',
        'label' => 'assistant_question_biggest'
    ],
    [
        'question' => 'Show my goals',
        'label' => 'assistant_question_goals'
    ],
];

// Existing health-score labels are generated in English.
// Translate known labels without changing their calculations.
function assistantHealthLabel(string $label): string
{
    $labels = [
        'Excellent' => 'assistant_health_excellent',
        'Good' => 'assistant_health_good',
        'Fair' => 'assistant_health_fair',
        'Needs Work' => 'assistant_health_needs_work',
        'Poor' => 'assistant_health_poor',
    ];

    return isset($labels[$label])
        ? t($labels[$label])
        : $label;
}

// Translate known health-score components
function assistantHealthPart(string $name): string
{
    $parts = [
        'Savings Rate' => 'assistant_part_savings',
        'Budget Control' => 'assistant_part_budgets',
        'Goals' => 'assistant_part_goals',
        'Balance' => 'assistant_part_balance',
    ];

    return isset($parts[$name])
        ? t($parts[$name])
        : $name;
}

// Page settings
$pageTitle = 'FinBot AI | FinTrack';
$activePage = 'assistant';

include __DIR__ . '/../includes/header.php';

?>

<!-- Page Header -->
<div class="container page-header">

    <span class="badge text-bg-primary mb-2">

        <i class="bi bi-stars me-1"></i>

        <?= e(t('assistant_smart')) ?>

    </span>

    <h1 class="h2 fw-bold mb-1">
        FinBot AI
    </h1>

    <p class="text-muted mb-0">
        <?= e(t('assistant_description')) ?>
    </p>

</div>


<div class="container pb-4">
    <div class="row g-4">

        <!-- Financial Health and Insights -->
        <div class="col-lg-5">

            <!-- Financial Health Score -->
            <div class="card p-4 mb-4">

                <div class="d-flex align-items-center gap-4">

                    <div
                        class="score-ring score-ring-lg"
                        style="--score: <?= (float) $health['score'] ?>">

                        <span>
                            <?= e($health['score']) ?>
                        </span>

                    </div>

                    <div>

                        <div class="text-muted small">
                            <?= e(t('assistant_health_score')) ?>
                        </div>

                        <div class="h4 fw-bold text-<?= e($health['color']) ?> mb-0">

                            <?= e(assistantHealthLabel(
                                $health['label']
                            )) ?>

                        </div>

                    </div>

                </div>

                <hr>


                <!-- Financial Health Components -->
                <?php foreach (
                    $health['parts'] as $name => [$got, $max]
                ): ?>

                    <div class="d-flex justify-content-between small mb-1 gap-2">

                        <span>
                            <?= e(assistantHealthPart($name)) ?>
                        </span>

                        <span class="fw-semibold">
                            <?= e($got) ?>/<?= e($max) ?>
                        </span>

                    </div>

                    <div
                        class="progress mb-2"
                        style="height: 6px">

                        <div
                            class="progress-bar bg-<?= e($health['color']) ?>"
                            style="width: <?= $max > 0
                                ? max(0, min(100, $got / $max * 100))
                                : 0 ?>%">
                        </div>

                    </div>

                <?php endforeach; ?>


                <p class="small text-muted mt-2 mb-0">

                    <i class="bi bi-info-circle"></i>

                    <?= e(t('assistant_health_explanation')) ?>

                </p>

            </div>


            <!-- Financial Insights -->
            <h2 class="h5 fw-bold mb-3">
                <?= e(t('assistant_insights')) ?>
            </h2>

            <?php foreach ($insights as $insight): ?>

                <div class="insight-card border-<?= e($insight['type']) ?>">

                    <div class="insight-icon text-<?= e($insight['type']) ?> bg-<?= e($insight['type']) ?>-subtle">

                        <i class="bi <?= e($insight['icon']) ?>"></i>

                    </div>

                    <div>

                        <div class="fw-semibold">
                            <?= e($insight['title']) ?>
                        </div>

                        <div class="small text-muted">
                            <?= e($insight['text']) ?>
                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>


        <!-- FinBot Chat -->
        <div class="col-lg-7">

            <div class="card chat-page">

                <!-- Chat Header -->
                <div class="chat-header">

                    <div class="d-flex align-items-center gap-2">

                        <span class="bot-avatar">
                            <i class="bi bi-robot"></i>
                        </span>

                        <div>

                            <div class="fw-bold">
                                <?= e(t('assistant_chat_title')) ?>
                            </div>

                            <div class="small opacity-75">
                                <?= e(t('assistant_chat_subtitle')) ?>
                            </div>

                        </div>

                    </div>

                </div>


                <!-- Chat Messages -->
                <div
                    class="chat-messages"
                    id="chatMessages">

                    <!-- Welcome Message -->
                    <div class="chat-msg bot">

                        <?= e(sprintf(
                            t('assistant_welcome'),
                            explode(' ', $_SESSION['user_name'])[0]
                        )) ?>

                    </div>


                    <!-- Existing Chat History -->
                    <?php foreach ($history as $message): ?>

                        <div class="chat-msg user">
                            <?= e($message['question']) ?>
                        </div>

                        <div class="chat-msg bot">
                            <?= e($message['answer']) ?>
                        </div>

                    <?php endforeach; ?>

                </div>


                <!-- Suggested Questions -->
                <div class="chat-suggestions">

                    <?php foreach ($suggestions as $suggestion): ?>

                        <button
                            type="button"
                            class="chip"
                            data-question="<?= e($suggestion['question']) ?>">

                            <?= e(t($suggestion['label'])) ?>

                        </button>

                    <?php endforeach; ?>

                </div>


                <!-- Chat Form -->
                <form
                    class="chat-form"
                    id="chatForm">

                    <input
                        type="text"
                        class="form-control"
                        id="chatInput"
                        placeholder="<?= e(t('assistant_chat_placeholder')) ?>"
                        aria-label="<?= e(t('ask_finbot')) ?>"
                        maxlength="300"
                        autocomplete="off"
                        required>

                    <button
                        type="submit"
                        class="btn btn-primary px-3"
                        aria-label="<?= e(t('send_message')) ?>">

                        <i class="bi bi-send"></i>

                    </button>

                </form>

            </div>

        </div>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
