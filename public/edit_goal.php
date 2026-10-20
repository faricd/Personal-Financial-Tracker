
<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/language.php';

requireLogin();

$userId = (int) $_SESSION["user_id"];
$id     = (int) ($_GET["id"] ?? 0);
$error  = "";

// Load the goal belonging to the logged-in user
$form = fetchOne(
    $conn,
    "SELECT *
     FROM savings_goals
     WHERE id = ? AND user_id = ?",
    "ii",
    [$id, $userId]
);

// Goal not found
if (!$form) {

    setFlash(
        "danger",
        t('goal_not_found')
    );

    header("Location: goals.php");
    exit;
}

// UPDATE: Save changes to the goal
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    verifyCsrfToken();

    [$form, $error] = validateGoal($_POST, false);

    if ($error === "") {

        $stmt = $conn->prepare(
            "UPDATE savings_goals
             SET title = ?,
                 target_amount = ?,
                 saved_amount = ?,
                 deadline = ?
             WHERE id = ? AND user_id = ?"
        );

        $stmt->bind_param(
            "sddsii",
            $form['title'],
            $form['target_amount'],
            $form['saved_amount'],
            $form['deadline'],
            $id,
            $userId
        );

        $stmt->execute();

        setFlash(
            "success",
            t('goal_updated_success')
        );

        header("Location: goals.php");
        exit;
    }
}

// Page configuration
$pageTitle = t('edit_goal') . " | FinTrack";
$activePage = "goals";

// Shared form button
$submitLabel =
    '<i class="bi bi-check2 me-1"></i>'
    . e(t('update_goal'));

$action = 'edit';

include __DIR__ . '/../includes/header.php';

?>

<!-- Page Header -->
<div class="container page-header">

    <a
        href="goals.php"
        class="small text-decoration-none">

        <i class="bi <?= $isRTL ? 'bi-arrow-right' : 'bi-arrow-left' ?>"></i>

        <?= e(t('back_to_goals')) ?>

    </a>

    <h1 class="h2 fw-bold mt-2">
        <?= e(t('edit_goal')) ?>
    </h1>

</div>


<!-- Edit Goal Form -->
<div class="container pb-4">

    <div
        class="card p-4 p-md-5 mx-auto"
        style="max-width: 520px">

        <!-- Validation Error -->
        <?php if ($error): ?>

            <div class="alert alert-danger">
                <?= e($error) ?>
            </div>

        <?php endif; ?>

        <!-- Shared Goal Form -->
        <?php include __DIR__ . '/../includes/goal_form.php'; ?>

    </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
