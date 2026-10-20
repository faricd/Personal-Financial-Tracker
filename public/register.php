
<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/language.php';

// Redirect users who are already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = "";
$name = "";
$email = "";
$userType = "student";

// Handle registration
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    verifyCsrfToken();

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $userType = $_POST["user_type"] ?? "";
    $password = $_POST["password"] ?? "";
    $confirm = $_POST["confirm_password"] ?? "";

    // Server-side validation
    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {

        $error = t('register_invalid_name');

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = t('login_email_validation');

    } elseif (!in_array($userType, ["student", "professional"], true)) {

        $error = t('register_invalid_user_type');

    } elseif (strlen($password) < 6) {

        $error = t('register_password_short');

    } elseif ($password !== $confirm) {

        $error = t('passwords_do_not_match');

    } elseif (fetchOne(
        $conn,
        "SELECT id FROM users WHERE email = ?",
        "s",
        [$email]
    )) {

        $error = t('register_email_exists');

    } else {

        // Hash the password before saving it
        $hash = password_hash($password, PASSWORD_DEFAULT);

        // Insert new user
        $stmt = $conn->prepare(
            "INSERT INTO users
             (name, email, password, user_type)
             VALUES (?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "ssss",
            $name,
            $email,
            $hash,
            $userType
        );

        $stmt->execute();

        // Create default categories for the new user
        createDefaultCategories(
            $conn,
            $conn->insert_id,
            $userType
        );

        // Redirect to login
        header("Location: login.php?registered=1");
        exit;
    }
}

// Page settings
$pageTitle = t('register_create_account') . " | FinTrack";

include __DIR__ . '/../includes/header.php';

?>

<div class="auth-page position-relative">

    <!-- Language Selector: Globe Icon Only -->
    <div class="position-absolute top-0 end-0 p-3">

        <div class="dropdown">

            <button
                type="button"
                class="btn border-0 text-white p-2"
                data-bs-toggle="dropdown"
                aria-expanded="false"
                aria-label="<?= e(t('language')) ?>"
                title="<?= e(t('language')) ?>">

                <i class="bi bi-globe2 fs-4"></i>

            </button>

            <ul class="dropdown-menu dropdown-menu-end shadow border-0">

                <!-- English -->
                <li>
                    <a
                        href="register.php?lang=en"
                        class="dropdown-item <?= $lang === 'en' ? 'active' : '' ?>">
                        English
                    </a>
                </li>

                <!-- Dari -->
                <li>
                    <a
                        href="register.php?lang=fa"
                        class="dropdown-item <?= $lang === 'fa' ? 'active' : '' ?>">
                        دری
                    </a>
                </li>

                <!-- Pashto -->
                <li>
                    <a
                        href="register.php?lang=ps"
                        class="dropdown-item <?= $lang === 'ps' ? 'active' : '' ?>">
                        پښتو
                    </a>
                </li>

            </ul>

        </div>

    </div>


    <div
        class="auth-card card p-4 p-md-5"
        style="max-width: 520px">

        <!-- Page Heading -->
        <div class="text-center mb-4">

            <div class="display-6 text-primary">
                <i class="bi bi-person-plus"></i>
            </div>

            <h1 class="h3 fw-bold mt-2">
                <?= e(t('register_create_account')) ?>
            </h1>

            <p class="text-muted mb-0">
                <?= e(t('register_subtitle')) ?>
            </p>

        </div>


        <!-- Validation Error -->
        <?php if ($error): ?>

            <div class="alert alert-danger">

                <i class="bi bi-exclamation-triangle me-1"></i>

                <?= e($error) ?>

            </div>

        <?php endif; ?>


        <!-- Registration Form -->
        <form
            method="POST"
            class="needs-validation"
            id="registerForm"
            novalidate>

            <?= csrfField() ?>


            <!-- Profile Type -->
            <label class="form-label">
                <?= e(t('register_i_am')) ?>
            </label>

            <div class="row g-2 mb-3">

                <!-- Student -->
                <div class="col-6">

                    <input
                        type="radio"
                        class="btn-check"
                        name="user_type"
                        id="typeStudent"
                        value="student"
                        <?= $userType === "student" ? "checked" : "" ?>
                        required>

                    <label
                        class="btn btn-outline-primary w-100 py-3 profile-option"
                        for="typeStudent">

                        <i class="bi bi-mortarboard d-block fs-4"></i>

                        <?= e(t('register_student')) ?>

                    </label>

                </div>


                <!-- Professional -->
                <div class="col-6">

                    <input
                        type="radio"
                        class="btn-check"
                        name="user_type"
                        id="typePro"
                        value="professional"
                        <?= $userType === "professional" ? "checked" : "" ?>>

                    <label
                        class="btn btn-outline-primary w-100 py-3 profile-option"
                        for="typePro">

                        <i class="bi bi-briefcase d-block fs-4"></i>

                        <?= e(t('register_professional')) ?>

                    </label>

                </div>

            </div>


            <!-- Full Name -->
            <div class="mb-3">

                <label
                    class="form-label"
                    for="name">

                    <?= e(t('register_full_name')) ?>

                </label>

                <input
                    type="text"
                    name="name"
                    id="name"
                    class="form-control"
                    value="<?= e($name) ?>"
                    autocomplete="name"
                    required
                    minlength="2"
                    maxlength="100">

                <div class="invalid-feedback">
                    <?= e(t('register_name_validation')) ?>
                </div>

            </div>


            <!-- Email -->
            <div class="mb-3">

                <label
                    class="form-label"
                    for="email">

                    <?= e(t('email')) ?>

                </label>

                <input
                    type="email"
                    name="email"
                    id="email"
                    class="form-control"
                    value="<?= e($email) ?>"
                    autocomplete="email"
                    required
                    maxlength="150">

                <div class="invalid-feedback">
                    <?= e(t('login_email_validation')) ?>
                </div>

            </div>


            <!-- Password -->
            <div class="mb-3">

                <label
                    class="form-label"
                    for="password">

                    <?= e(t('password')) ?>

                </label>

                <div class="input-group has-validation">

                    <input
                        type="password"
                        name="password"
                        id="password"
                        class="form-control"
                        autocomplete="new-password"
                        required
                        minlength="6">

                    <!-- Show / Hide Password -->
                    <button
                        type="button"
                        class="btn btn-outline-secondary toggle-password"
                        data-target="password"
                        aria-label="<?= e(t('login_toggle_password')) ?>">

                        <i class="bi bi-eye"></i>

                    </button>

                    <div class="invalid-feedback">
                        <?= e(t('register_password_validation')) ?>
                    </div>

                </div>


                <!-- Password Strength Bar -->
                <div
                    class="progress mt-2"
                    style="height: 6px">

                    <div
                        class="progress-bar"
                        id="strengthBar"
                        style="width: 0">
                    </div>

                </div>

                <small
                    class="text-muted"
                    id="strengthText">

                    <?= e(t('password_strength')) ?>

                </small>

            </div>


            <!-- Confirm Password -->
            <div class="mb-4">

                <label
                    class="form-label"
                    for="confirm_password">

                    <?= e(t('register_confirm_password')) ?>

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


            <!-- Create Account Button -->
            <button
                type="submit"
                class="btn btn-primary w-100 py-2">

                <?= e(t('register_create_account')) ?>

            </button>

        </form>


        <!-- Login Link -->
        <p class="text-center mt-4 mb-0">

            <?= e(t('register_already_registered')) ?>

            <a href="login.php">
                <?= e(t('login')) ?>
            </a>

        </p>

    </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
