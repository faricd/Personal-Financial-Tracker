
<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/language.php';

requireLogin();

$userId = (int) $_SESSION["user_id"];
$profileError = "";
$passwordError = "";

$user = fetchOne(
    $conn,
    "SELECT * FROM users WHERE id = ?",
    "i",
    [$userId]
);

// Handle profile actions
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    verifyCsrfToken();

    $action = $_POST['action'] ?? '';

    // Update profile details
    if ($action === 'profile') {

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $userType = $_POST['user_type'] ?? '';
        $currency = $_POST['currency'] ?? '';

        if (
            mb_strlen($name) < 2 ||
            mb_strlen($name) > 100
        ) {

            $profileError = t('profile_invalid_name');

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $profileError = t('profile_invalid_email');

        } elseif (
            !in_array(
                $userType,
                ['student', 'professional'],
                true
            ) ||
            !array_key_exists($currency, currencies())
        ) {

            $profileError = t('profile_invalid_options');

        } elseif (fetchOne(
            $conn,
            "SELECT id FROM users
             WHERE email = ? AND id <> ?",
            "si",
            [$email, $userId]
        )) {

            $profileError = t('profile_email_exists');

        } else {

            $stmt = $conn->prepare(
                "UPDATE users
                 SET name = ?, email = ?,
                     user_type = ?, currency = ?
                 WHERE id = ?"
            );

            $stmt->bind_param(
                "ssssi",
                $name,
                $email,
                $userType,
                $currency,
                $userId
            );

            $stmt->execute();

            // Keep session up to date
            $_SESSION['user_name'] = $name;
            $_SESSION['user_type'] = $userType;
            $_SESSION['currency'] = $currency;

            setFlash(
                "success",
                t('profile_updated_success')
            );

            header("Location: profile.php");
            exit;
        }

        // Preserve submitted values if validation fails
        $user = array_merge(
            $user,
            [
                'name' => $name,
                'email' => $email,
                'user_type' => $userType,
                'currency' => $currency
            ]
        );
    }

    // Change password
    if ($action === 'password') {

        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!password_verify($current, $user['password'])) {

            $passwordError = t('profile_wrong_password');

        } elseif (strlen($new) < 6) {

            $passwordError = t('profile_password_short');

        } elseif ($new !== $confirm) {

            $passwordError = t('profile_password_mismatch');

        } else {

            $hash = password_hash(
                $new,
                PASSWORD_DEFAULT
            );

            $stmt = $conn->prepare(
                "UPDATE users
                 SET password = ?
                 WHERE id = ?"
            );

            $stmt->bind_param(
                "si",
                $hash,
                $userId
            );

            $stmt->execute();

            setFlash(
                "success",
                t('profile_password_updated')
            );

            header("Location: profile.php");
            exit;
        }
    }

    // Clear FinBot chat history
    if ($action === 'clear_chat') {

        $stmt = $conn->prepare(
            "DELETE FROM ai_chat_history
             WHERE user_id = ?"
        );

        $stmt->bind_param("i", $userId);
        $stmt->execute();

        setFlash(
            "success",
            t('profile_chat_cleared')
        );

        header("Location: profile.php");
        exit;
    }
}

// Account statistics
$stats = fetchOne(
    $conn,
    "SELECT
        (SELECT COUNT(*) FROM transactions
         WHERE user_id = ?) AS transactions,
        (SELECT COUNT(*) FROM categories
         WHERE user_id = ?) AS categories,
        (SELECT COUNT(*) FROM savings_goals
         WHERE user_id = ?) AS goals,
        (SELECT COUNT(*) FROM ai_chat_history
         WHERE user_id = ?) AS chats",
    "iiii",
    [$userId, $userId, $userId, $userId]
);

// Localized member-since date
$joined = strtotime($user['created_at']);

$memberSince =
    t('month_' . date('n', $joined))
    . ' ' . date('Y', $joined);

// Page settings
$pageTitle = t('profile_settings') . " | FinTrack";
$activePage = "profile";

include __DIR__ . '/../includes/header.php';

?>

<!-- Page Header -->
<div class="container page-header">

    <h1 class="h2 fw-bold mb-1">
        <?= e(t('profile_settings')) ?>
    </h1>

    <p class="text-muted mb-0">

        <?= e(t('member_since')) ?>

        <?= e($memberSince) ?>

    </p>

</div>


<div class="container pb-4">
    <div class="row g-4">

        <!-- Left Column -->
        <div class="col-lg-4">

            <!-- Profile Summary -->
            <div class="card p-4 text-center mb-4">

                <div class="avatar avatar-lg mx-auto mb-3">
                    <?= e(mb_strtoupper(
                        mb_substr($user['name'], 0, 1)
                    )) ?>
                </div>

                <h2 class="h5 fw-bold mb-0">
                    <?= e($user['name']) ?>
                </h2>

                <p class="text-muted small">
                    <?= e($user['email']) ?>
                </p>

                <!-- User Type -->
                <span class="badge text-bg-primary mx-auto">

                    <?= e(t(
                        $user['user_type'] === 'student'
                            ? 'register_student'
                            : 'register_professional'
                    )) ?>

                </span>


                <!-- Account Statistics -->
                <div class="row g-2 mt-3 text-start">

                    <div class="col-6">

                        <div class="mini-stat">

                            <span>
                                <?= e(t('transactions')) ?>
                            </span>

                            <b><?= (int) $stats['transactions'] ?></b>

                        </div>

                    </div>

                    <div class="col-6">

                        <div class="mini-stat">

                            <span>
                                <?= e(t('categories')) ?>
                            </span>

                            <b><?= (int) $stats['categories'] ?></b>

                        </div>

                    </div>

                    <div class="col-6">

                        <div class="mini-stat">

                            <span>
                                <?= e(t('profile_goals')) ?>
                            </span>

                            <b><?= (int) $stats['goals'] ?></b>

                        </div>

                    </div>

                    <div class="col-6">

                        <div class="mini-stat">

                            <span>
                                <?= e(t('profile_finbot_chats')) ?>
                            </span>

                            <b><?= (int) $stats['chats'] ?></b>

                        </div>

                    </div>

                </div>

            </div>


            <!-- FinBot History -->
            <div class="card p-4">

                <h2 class="h6 fw-bold">
                    <?= e(t('profile_finbot_history')) ?>
                </h2>

                <p class="small text-muted">
                    <?= e(t('profile_clear_chat_description')) ?>
                </p>

                <form method="POST" class="delete-form">

                    <?= csrfField() ?>

                    <input
                        type="hidden"
                        name="action"
                        value="clear_chat">

                    <button
                        type="button"
                        class="btn btn-outline-danger w-100 delete-btn"
                        data-title="<?= e(t('profile_clear_chat_confirm')) ?>">

                        <i class="bi bi-trash me-1"></i>

                        <?= e(t('profile_clear_chat')) ?>

                    </button>

                </form>

            </div>

        </div>


        <!-- Right Column -->
        <div class="col-lg-8">

            <!-- Account Details -->
            <div class="card p-4 mb-4">

                <h2 class="h5 fw-bold mb-3">
                    <?= e(t('profile_account_details')) ?>
                </h2>

                <?php if ($profileError): ?>

                    <div class="alert alert-danger">
                        <?= e($profileError) ?>
                    </div>

                <?php endif; ?>


                <form
                    method="POST"
                    class="needs-validation"
                    novalidate>

                    <?= csrfField() ?>

                    <input
                        type="hidden"
                        name="action"
                        value="profile">

                    <div class="row g-3">

                        <!-- Full Name -->
                        <div class="col-md-6">

                            <label class="form-label" for="profileName">
                                <?= e(t('register_full_name')) ?>
                            </label>

                            <input
                                type="text"
                                name="name"
                                id="profileName"
                                class="form-control"
                                value="<?= e($user['name']) ?>"
                                required
                                minlength="2"
                                maxlength="100">

                            <div class="invalid-feedback">
                                <?= e(t('register_name_validation')) ?>
                            </div>

                        </div>


                        <!-- Email -->
                        <div class="col-md-6">

                            <label class="form-label" for="profileEmail">
                                <?= e(t('email')) ?>
                            </label>

                            <input
                                type="email"
                                name="email"
                                id="profileEmail"
                                class="form-control"
                                value="<?= e($user['email']) ?>"
                                required>

                            <div class="invalid-feedback">
                                <?= e(t('login_email_validation')) ?>
                            </div>

                        </div>


                        <!-- Profile Type -->
                        <div class="col-md-6">

                            <label class="form-label" for="profileType">
                                <?= e(t('profile_type')) ?>
                            </label>

                            <select
                                name="user_type"
                                id="profileType"
                                class="form-select">

                                <option
                                    value="student"
                                    <?= $user['user_type'] === 'student'
                                        ? 'selected' : '' ?>>

                                    <?= e(t('register_student')) ?>

                                </option>

                                <option
                                    value="professional"
                                    <?= $user['user_type'] === 'professional'
                                        ? 'selected' : '' ?>>

                                    <?= e(t('register_professional')) ?>

                                </option>

                            </select>

                            <div class="form-text">
                                <?= e(t('profile_type_help')) ?>
                            </div>

                        </div>


                        <!-- Currency -->
                        <div class="col-md-6">

                            <label class="form-label" for="profileCurrency">
                                <?= e(t('profile_currency')) ?>
                            </label>

                            <select
                                name="currency"
                                id="profileCurrency"
                                class="form-select">

                                <?php foreach (
                                    currencies() as $symbol => $label
                                ): ?>

                                    <option
                                        value="<?= e($symbol) ?>"
                                        <?= $user['currency'] === $symbol
                                            ? 'selected' : '' ?>>

                                        <?= e($label) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                            <div class="form-text">
                                <?= e(t('profile_currency_help')) ?>
                            </div>

                        </div>

                    </div>


                    <!-- Save Changes -->
                    <button
                        type="submit"
                        class="btn btn-primary mt-4">

                        <i class="bi bi-check2 me-1"></i>

                        <?= e(t('profile_save_changes')) ?>

                    </button>

                </form>

            </div>


            <!-- Change Password -->
            <div class="card p-4">

                <h2 class="h5 fw-bold mb-3">
                    <?= e(t('profile_change_password')) ?>
                </h2>

                <?php if ($passwordError): ?>

                    <div class="alert alert-danger">
                        <?= e($passwordError) ?>
                    </div>

                <?php endif; ?>


                <form
                    method="POST"
                    class="needs-validation"
                    id="passwordForm"
                    novalidate>

                    <?= csrfField() ?>

                    <input
                        type="hidden"
                        name="action"
                        value="password">

                    <div class="row g-3">

                        <!-- Current Password -->
                        <div class="col-md-4">

                            <label class="form-label" for="currentPassword">
                                <?= e(t('profile_current_password')) ?>
                            </label>

                            <input
                                type="password"
                                name="current_password"
                                id="currentPassword"
                                class="form-control"
                                autocomplete="current-password"
                                required>

                            <div class="invalid-feedback">
                                <?= e(t('profile_required')) ?>
                            </div>

                        </div>


                        <!-- New Password -->
                        <div class="col-md-4">

                            <label class="form-label" for="password">
                                <?= e(t('profile_new_password')) ?>
                            </label>

                            <input
                                type="password"
                                name="new_password"
                                id="password"
                                class="form-control"
                                autocomplete="new-password"
                                required
                                minlength="6">

                            <div class="invalid-feedback">
                                <?= e(t('register_password_validation')) ?>
                            </div>

                        </div>


                        <!-- Confirm New Password -->
                        <div class="col-md-4">

                            <label class="form-label" for="confirm_password">
                                <?= e(t('profile_confirm_new_password')) ?>
                            </label>

                            <input
                                type="password"
                                name="confirm_password"
                                id="confirm_password"
                                class="form-control"
                                autocomplete="new-password"
                                required
                                minlength="6">

                            <div class="invalid-feedback">
                                <?= e(t('passwords_do_not_match')) ?>
                            </div>

                        </div>

                    </div>


                    <!-- Update Password -->
                    <button
                        type="submit"
                        class="btn btn-dark mt-4">

                        <i class="bi bi-shield-lock me-1"></i>

                        <?= e(t('profile_update_password')) ?>

                    </button>

                </form>

            </div>

        </div>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
