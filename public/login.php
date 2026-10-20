
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
$email = "";

// Handle login
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    verifyCsrfToken();

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    // Validate email and password
    if (
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        $password === ""
    ) {

        $error = t('login_invalid_input');

    } else {

        $user = fetchOne(
            $conn,
            "SELECT * FROM users WHERE email = ?",
            "s",
            [$email]
        );

        // Verify hashed password
        if (
            $user &&
            password_verify($password, $user["password"])
        ) {

            // Protect against session fixation
            session_regenerate_id(true);

            $_SESSION["user_id"] = $user["id"];
            $_SESSION["user_name"] = $user["name"];
            $_SESSION["user_type"] = $user["user_type"];
            $_SESSION["currency"] = $user["currency"];

            setFlash(
                "success",
                sprintf(
                    t('login_welcome_user'),
                    $user["name"]
                )
            );

            header("Location: dashboard.php");
            exit;
        }

        $error = t('login_invalid_credentials');
    }
}

// Page settings
$pageTitle = t('login') . " | FinTrack";

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
                        href="login.php?lang=en"
                        class="dropdown-item <?= $lang === 'en' ? 'active' : '' ?>">
                        English
                    </a>
                </li>

                <!-- Dari -->
                <li>
                    <a
                        href="login.php?lang=fa"
                        class="dropdown-item <?= $lang === 'fa' ? 'active' : '' ?>">
                        دری
                    </a>
                </li>

                <!-- Pashto -->
                <li>
                    <a
                        href="login.php?lang=ps"
                        class="dropdown-item <?= $lang === 'ps' ? 'active' : '' ?>">
                        پښتو
                    </a>
                </li>

            </ul>

        </div>

    </div>


    <div class="auth-wrapper">

        <!-- Left: Introduction -->
        <div class="auth-intro d-none d-lg-flex">

            <div>

                <div class="brand-mark mb-4">
                    <i class="bi bi-wallet2"></i>
                    FinTrack
                </div>

                <h2 class="fw-bold mb-3">
                    <?= e(t('login_intro_title')) ?>
                </h2>

                <p class="opacity-75 mb-4">
                    <?= e(t('login_intro_description')) ?>
                </p>

                <!-- Features -->
                <ul class="feature-list">

                    <li>
                        <i class="bi bi-check2-circle"></i>
                        <?= e(t('login_feature_transactions')) ?>
                    </li>

                    <li>
                        <i class="bi bi-check2-circle"></i>
                        <?= e(t('login_feature_budgets')) ?>
                    </li>

                    <li>
                        <i class="bi bi-check2-circle"></i>
                        <?= e(t('login_feature_goals')) ?>
                    </li>

                    <li>
                        <i class="bi bi-check2-circle"></i>
                        <?= e(t('login_feature_finbot')) ?>
                    </li>

                </ul>

            </div>

        </div>


        <!-- Right: Login Form -->
        <div class="auth-card card p-4 p-md-5">

            <!-- Welcome Heading -->
            <div class="mb-4">

                <h1 class="h3 fw-bold">
                    <?= e(t('login_welcome_back')) ?> 👋
                </h1>

                <p class="text-muted mb-0">
                    <?= e(t('login_subtitle')) ?>
                </p>

            </div>


            <!-- Registration Success -->
            <?php if (isset($_GET["registered"])): ?>

                <div class="alert alert-success">

                    <i class="bi bi-check-circle me-1"></i>

                    <?= e(t('login_account_created')) ?>

                </div>

            <!-- Logout Message -->
            <?php elseif (isset($_GET["logged_out"])): ?>

                <div class="alert alert-info">

                    <i class="bi bi-box-arrow-right me-1"></i>

                    <?= e(t('login_logged_out')) ?>

                </div>

            <?php endif; ?>


            <!-- Login Error -->
            <?php if ($error): ?>

                <div class="alert alert-danger">

                    <i class="bi bi-exclamation-triangle me-1"></i>

                    <?= e($error) ?>

                </div>

            <?php endif; ?>


            <!-- Login Form -->
            <form
                method="POST"
                class="needs-validation"
                novalidate>

                <?= csrfField() ?>

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
                        required>

                    <div class="invalid-feedback">
                        <?= e(t('login_email_validation')) ?>
                    </div>

                </div>


                <!-- Password -->
                <div class="mb-4">

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
                            autocomplete="current-password"
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
                            <?= e(t('login_password_validation')) ?>
                        </div>

                    </div>

                </div>


                <!-- Login Button -->
                <button
                    type="submit"
                    class="btn btn-primary w-100 py-2">

                    <?= e(t('login')) ?>

                </button>

            </form>


            <!-- Demo Account -->
            <div class="demo-box mt-4">

                <i class="bi bi-info-circle me-1"></i>

                <?= e(t('login_demo_account')) ?>:

                <b>demo@fintrack.com</b>
                /
                <b>demo123</b>

            </div>


            <!-- Registration Link -->
            <p class="text-center mt-4 mb-0">

                <?= e(t('login_no_account')) ?>

                <a href="register.php">
                    <?= e(t('login_create_account')) ?>
                </a>

            </p>

        </div>

    </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
