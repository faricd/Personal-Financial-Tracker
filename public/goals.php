
<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ai_helper.php';
require_once __DIR__ . '/../includes/language.php';

requireLogin();

$userId = (int) $_SESSION["user_id"];
$error = "";

$form = [
    'title' => '',
    'target_amount' => '',
    'saved_amount' => '',
    'deadline' => ''
];

// Localized Gregorian date
function goalDateLabel(string $date): string
{
    $timestamp = strtotime($date);

    if ($timestamp === false) {
        return $date;
    }

    return date('d', $timestamp) . ' ' .
        t('month_' . date('n', $timestamp)) . ' ' .
        date('Y', $timestamp);
}

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    verifyCsrfToken();

    $action = $_POST['action'] ?? '';

    // CREATE a goal
    if ($action === 'add') {

        [$form, $error] = validateGoal($_POST);

        if ($error === "") {

            $stmt = $conn->prepare(
                "INSERT INTO savings_goals
                (user_id, title, target_amount, saved_amount, deadline)
                VALUES (?, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "isdds",
                $userId,
                $form['title'],
                $form['target_amount'],
                $form['saved_amount'],
                $form['deadline']
            );

            $stmt->execute();

            setFlash(
                "success",
                sprintf(
                    t('goal_created_success'),
                    $form['title']
                )
            );

            header("Location: goals.php");
            exit;
        }
    }

    // UPDATE: Add money to an existing goal
    if ($action === 'deposit') {

        $id = (int) ($_POST['id'] ?? 0);
        $amount = (float) ($_POST['amount'] ?? 0);

        if ($amount <= 0) {

            setFlash(
                "danger",
                t('goal_invalid_deposit')
            );

        } else {

            $stmt = $conn->prepare(
                "UPDATE savings_goals
                 SET saved_amount = saved_amount + ?
                 WHERE id = ? AND user_id = ?"
            );

            $stmt->bind_param(
                "dii",
                $amount,
                $id,
                $userId
            );

            $stmt->execute();

            if ($stmt->affected_rows > 0) {

                setFlash(
                    "success",
                    sprintf(
                        t('goal_deposit_success'),
                        money($amount)
                    )
                );

            } else {

                setFlash(
                    "danger",
                    t('goal_not_found')
                );
            }
        }

        header("Location: goals.php");
        exit;
    }

    // DELETE a goal
    if ($action === 'delete') {

        $id = (int) ($_POST['id'] ?? 0);

        $stmt = $conn->prepare(
            "DELETE FROM savings_goals
             WHERE id = ? AND user_id = ?"
        );

        $stmt->bind_param(
            "ii",
            $id,
            $userId
        );

        $stmt->execute();

        setFlash(
            $stmt->affected_rows > 0 ? "success" : "danger",
            $stmt->affected_rows > 0
                ? t('goal_deleted_success')
                : t('goal_not_found')
        );

        header("Location: goals.php");
        exit;
    }
}

// READ: Goals and summary calculations
$goals = getGoals($conn, $userId);

$totalSaved = array_sum(
    array_column($goals, 'saved_amount')
);

$totalTarget = array_sum(
    array_column($goals, 'target_amount')
);

// Page settings
$pageTitle = t('savings_goals') . " | FinTrack";
$activePage = "goals";

include __DIR__ . '/../includes/header.php';

?>

<!-- Page Header -->
<div class="container page-header">

    <h1 class="h2 fw-bold mb-1">
        <?= e(t('savings_goals')) ?>
    </h1>

    <p class="text-muted mb-0">
        <?= e(t('goals_description')) ?>
    </p>

</div>


<div class="container pb-4">

    <div class="row g-4">

        <!-- Add New Goal -->
        <div class="col-lg-4">

            <div class="card p-4">

                <h2 class="h5 fw-bold mb-3">
                    <?= e(t('new_goal')) ?>
                </h2>

                <?php if ($error): ?>

                    <div class="alert alert-danger">
                        <?= e($error) ?>
                    </div>

                <?php endif; ?>

                <?php
                    $submitLabel =
                        '<i class="bi bi-plus-lg me-1"></i>'
                        . e(t('create_goal'));

                    $action = 'add';

                    include __DIR__ . '/../includes/goal_form.php';
                ?>

            </div>

        </div>


        <!-- Savings Goals -->
        <div class="col-lg-8">

            <!-- Summary Cards -->
            <div class="row g-3 mb-4">

                <div class="col-6">

                    <div class="mini-stat">

                        <span>
                            <?= e(t('total_saved')) ?>
                        </span>

                        <b class="amount-income">
                            <?= money($totalSaved) ?>
                        </b>

                    </div>

                </div>

                <div class="col-6">

                    <div class="mini-stat">

                        <span>
                            <?= e(t('total_target')) ?>
                        </span>

                        <b>
                            <?= money($totalTarget) ?>
                        </b>

                    </div>

                </div>

            </div>


            <!-- No Goals -->
            <?php if (!$goals): ?>

                <div class="card p-4">

                    <div class="empty-state">

                        <i class="bi bi-piggy-bank"></i>

                        <p>
                            <?= e(t('no_goals_yet')) ?>
                        </p>

                    </div>

                </div>

            <?php endif; ?>


            <!-- Goal Cards -->
            <div class="row g-4">

                <?php foreach ($goals as $g):

                    $remaining = max(
                        0,
                        $g['target_amount'] - $g['saved_amount']
                    );

                    $done = $remaining == 0;

                    $percent = max(
                        0,
                        min(100, (float) $g['percent'])
                    );

                ?>

                    <div class="col-md-6">

                        <div class="card goal-card p-4 h-100 <?= $done ? 'goal-done' : '' ?>">

                            <!-- Goal Header -->
                            <div class="d-flex justify-content-between align-items-start mb-2">

                                <div>

                                    <h3 class="h6 fw-bold mb-1">

                                        <?= $done ? '🎉 ' : '' ?>

                                        <?= e($g['title']) ?>

                                    </h3>

                                    <div class="small text-muted">

                                        <?php if ($g['deadline']): ?>

                                            <i class="bi bi-calendar-event"></i>

                                            <?= e(t('goal_due_by')) ?>

                                            <?= e(goalDateLabel($g['deadline'])) ?>

                                        <?php else: ?>

                                            <i class="bi bi-infinity"></i>

                                            <?= e(t('no_deadline')) ?>

                                        <?php endif; ?>

                                    </div>

                                </div>


                                <!-- Edit and Delete Dropdown -->
                                <div class="dropdown">

                                    <button
                                        type="button"
                                        class="btn btn-sm btn-light"
                                        data-bs-toggle="dropdown"
                                        aria-label="<?= e(t('actions')) ?>">

                                        <i class="bi bi-three-dots"></i>

                                    </button>

                                    <ul class="dropdown-menu dropdown-menu-end">

                                        <!-- Edit -->
                                        <li>

                                            <a
                                                class="dropdown-item"
                                                href="edit_goal.php?id=<?= (int) $g['id'] ?>">

                                                <i class="bi bi-pencil me-2"></i>

                                                <?= e(t('edit')) ?>

                                            </a>

                                        </li>


                                        <!-- Delete -->
                                        <li>

                                            <form
                                                method="POST"
                                                class="delete-form">

                                                <?= csrfField() ?>

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="delete">

                                                <input
                                                    type="hidden"
                                                    name="id"
                                                    value="<?= (int) $g['id'] ?>">

                                                <button
                                                    type="button"
                                                    class="dropdown-item text-danger delete-btn"
                                                    data-title="<?= e(sprintf(
                                                        t('delete_goal_confirm'),
                                                        $g['title']
                                                    )) ?>">

                                                    <i class="bi bi-trash me-2"></i>

                                                    <?= e(t('delete')) ?>

                                                </button>

                                            </form>

                                        </li>

                                    </ul>

                                </div>

                            </div>


                            <!-- Saved vs Target -->
                            <div class="d-flex align-items-end justify-content-between my-2">

                                <div class="fs-4 fw-bold">
                                    <?= money($g['saved_amount']) ?>
                                </div>

                                <div class="small text-muted">

                                    <?= e(t('of')) ?>

                                    <?= money($g['target_amount']) ?>

                                </div>

                            </div>


                            <!-- Progress Bar -->
                            <div
                                class="progress mb-2"
                                style="height: 10px">

                                <div
                                    class="progress-bar <?= $done ? 'bg-success' : '' ?>"
                                    role="progressbar"
                                    style="width: <?= $percent ?>%"
                                    aria-valuenow="<?= $percent ?>"
                                    aria-valuemin="0"
                                    aria-valuemax="100">
                                </div>

                            </div>


                            <!-- Goal Progress -->
                            <div class="small text-muted mb-3">

                                <?= e((string) $g['percent']) ?>%

                                &middot;

                                <?php if ($done): ?>

                                    <?= e(t('goal_reached')) ?>

                                <?php else: ?>

                                    <?= e(sprintf(
                                        t('goal_amount_remaining'),
                                        money($remaining)
                                    )) ?>

                                <?php endif; ?>

                            </div>


                            <!-- Deposit Money -->
                            <?php if (!$done): ?>

                                <form
                                    method="POST"
                                    class="input-group input-group-sm mt-auto">

                                    <?= csrfField() ?>

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="deposit">

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int) $g['id'] ?>">

                                    <input
                                        type="number"
                                        name="amount"
                                        class="form-control"
                                        min="1"
                                        step="0.01"
                                        placeholder="<?= e(t('amount')) ?>"
                                        aria-label="<?= e(t('amount')) ?>"
                                        required>

                                    <button
                                        type="submit"
                                        class="btn btn-success">

                                        <i class="bi bi-plus-lg"></i>

                                        <?= e(t('add_money')) ?>

                                    </button>

                                </form>

                            <?php endif; ?>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        </div>

    </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
