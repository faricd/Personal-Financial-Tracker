
<?php
/*
 * FinTrack - Header and Navigation
 * English, Dari, Pashto
 */

require_once __DIR__ . '/language.php';

$activePage = $activePage ?? '';
$isRTL = in_array($lang, ['fa', 'ps'], true);

// Main navigation links
$menu = [
    'dashboard' => [
        t('dashboard'),
        'bi-speedometer2',
        'dashboard.php'
    ],
    'transactions' => [
        t('transactions'),
        'bi-arrow-left-right',
        'transactions.php'
    ],
    'categories' => [
        t('categories'),
        'bi-tags',
        'categories.php'
    ],
    'budgets' => [
        t('budgets'),
        'bi-pie-chart',
        'budgets.php'
    ],
];

// Links inside Insights & Tools
$toolsMenu = [
    'goals' => [
        t('goals'),
        'bi-piggy-bank',
        'goals.php'
    ],
    'reports' => [
        t('reports'),
        'bi-bar-chart-line',
        'reports.php'
    ],
    'assistant' => [
        t('assistant'),
        'bi-robot',
        'assistant.php'
    ],
];

$toolsActive = array_key_exists(
    $activePage,
    $toolsMenu
);

// Preserve current GET filters when switching languages
$languageUrls = [];

foreach (['en', 'fa', 'ps'] as $code) {
    $params = array_merge($_GET, ['lang' => $code]);
    $languageUrls[$code] = '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html
    lang="<?= e($lang) ?>"
    dir="<?= $isRTL ? 'rtl' : 'ltr' ?>"
    translate="no">

<head>
    <meta charset="UTF-8">
    <meta name="google" content="notranslate">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <meta
        name="csrf-token"
        content="<?= e($_SESSION['csrf_token'] ?? '') ?>">

    <title>
        <?= e($pageTitle ?? 'FinTrack') ?>
    </title>

    <!-- Bootstrap CSS -->
    <?php if ($isRTL): ?>

        <link
            href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css"
            rel="stylesheet">

    <?php else: ?>

        <link
            href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
            rel="stylesheet">

    <?php endif; ?>

    <!-- Bootstrap Icons -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet">

    <!-- Fonts -->
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Naskh+Arabic:wght@400;500;600;700&display=swap"
        rel="stylesheet">

    <!-- Custom Styles -->
    <link
        rel="stylesheet"
        href="assets/css/style.css">

    <!-- Charts -->
    <?php if (!empty($loadCharts)): ?>

        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.js"></script>

        <script>
            function chartColors() {
                return [
                    '#1f4f99',
                    '#e05252',
                    '#1f9d6b',
                    '#f0a202',
                    '#7b5ea7',
                    '#2bb3c0',
                    '#e8743b',
                    '#8c97a8',
                    '#d4508f',
                    '#5c7cfa'
                ];
            }

            Chart.defaults.font.family =
                <?= json_encode(
                    $isRTL
                        ? 'Noto Naskh Arabic, sans-serif'
                        : 'Inter, system-ui, sans-serif'
                ) ?>;
        </script>

    <?php endif; ?>

</head>

<body>

<?php if (isset($_SESSION['user_id'])): ?>

<nav class="navbar navbar-expand-lg navbar-dark app-navbar sticky-top">

    <div class="container">

        <!-- Brand -->
        <a
            class="navbar-brand fw-bold"
            href="dashboard.php">

            <i class="bi bi-wallet2 me-2"></i>
            FinTrack

        </a>

        <!-- Mobile Menu Button -->
        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#mainNav"
            aria-controls="mainNav"
            aria-expanded="false"
            aria-label="<?= e(t('toggle_navigation')) ?>">

            <span class="navbar-toggler-icon"></span>

        </button>

        <!-- Navigation Container -->
        <div
            class="collapse navbar-collapse"
            id="mainNav">

            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">

                <!-- Main Navigation -->
                <?php foreach (
                    $menu as $key => [$label, $icon, $link]
                ): ?>

                    <li class="nav-item">

                        <a
                            href="<?= e($link) ?>"
                            class="nav-link <?= $activePage === $key ? 'active' : '' ?>"
                            <?= $activePage === $key
                                ? 'aria-current="page"'
                                : '' ?>>

                            <i class="bi <?= e($icon) ?> me-1 d-lg-none d-xl-inline"></i>

                            <?= e($label) ?>

                        </a>

                    </li>

                <?php endforeach; ?>


                <!-- Insights & Tools Dropdown -->
                <li class="nav-item dropdown">

                    <button
                        class="nav-link dropdown-toggle <?= $toolsActive ? 'active' : '' ?>"
                        type="button"
                        data-bs-toggle="dropdown"
                        aria-expanded="false">

                        <i class="bi bi-grid me-1"></i>

                        <?= e(t('insights')) ?>

                    </button>

                    <ul class="dropdown-menu dropdown-menu-end shadow border-0">

                        <?php foreach (
                            $toolsMenu as $key => [$label, $icon, $link]
                        ): ?>

                            <li>

                                <a
                                    href="<?= e($link) ?>"
                                    class="dropdown-item d-flex align-items-center gap-2 <?= $activePage === $key ? 'active' : '' ?>"
                                    <?= $activePage === $key
                                        ? 'aria-current="page"'
                                        : '' ?>>

                                    <i class="bi <?= e($icon) ?>"></i>

                                    <span>
                                        <?= e($label) ?>
                                    </span>

                                </a>

                            </li>

                        <?php endforeach; ?>

                    </ul>

                </li>


                <!-- Language Dropdown -->
                <li class="nav-item dropdown ms-lg-2">

                    <button
                        class="nav-link dropdown-toggle language-toggle"
                        type="button"
                        data-bs-toggle="dropdown"
                        aria-expanded="false"
                        aria-label="<?= e(t('language')) ?>"
                        title="<?= e(t('language')) ?>">

                        <i class="bi bi-globe2 fs-5"></i>

                    </button>

                    <ul class="dropdown-menu dropdown-menu-end shadow border-0">

                        <li>
                            <a
                                class="dropdown-item <?= $lang === 'en' ? 'active' : '' ?>"
                                href="<?= e($languageUrls['en']) ?>">

                                English

                            </a>
                        </li>

                        <li>
                            <a
                                class="dropdown-item <?= $lang === 'fa' ? 'active' : '' ?>"
                                href="<?= e($languageUrls['fa']) ?>">

                                دری

                            </a>
                        </li>

                        <li>
                            <a
                                class="dropdown-item <?= $lang === 'ps' ? 'active' : '' ?>"
                                href="<?= e($languageUrls['ps']) ?>">

                                پښتو

                            </a>
                        </li>

                    </ul>

                </li>


                <!-- User Dropdown -->
                <li class="nav-item dropdown ms-lg-2">

                    <button
                        class="nav-link dropdown-toggle user-chip"
                        type="button"
                        data-bs-toggle="dropdown"
                        aria-expanded="false">

                        <span class="avatar">

                            <?= e(mb_strtoupper(
                                mb_substr(
                                    $_SESSION['user_name'] ?? 'U',
                                    0,
                                    1
                                )
                            )) ?>

                        </span>

                        <span class="d-lg-none d-xxl-inline">

                            <?= e($_SESSION['user_name'] ?? '') ?>

                        </span>

                    </button>

                    <ul class="dropdown-menu dropdown-menu-end shadow border-0">

                        <li>

                            <span class="dropdown-item-text small text-muted">

                                <?= e(t('welcome')) ?>,

                                <b>
                                    <?= e($_SESSION['user_name'] ?? '') ?>
                                </b>

                            </span>

                        </li>

                        <li>
                            <hr class="dropdown-divider">
                        </li>

                        <!-- Profile -->
                        <li>

                            <a
                                class="dropdown-item"
                                href="profile.php">

                                <i class="bi bi-person-gear me-2"></i>

                                <?= e(t('profile')) ?>

                            </a>

                        </li>

                        <!-- Logout -->
                        <li>

                            <a
                                class="dropdown-item text-danger"
                                href="logout.php">

                                <i class="bi bi-box-arrow-right me-2"></i>

                                <?= e(t('logout')) ?>

                            </a>

                        </li>

                    </ul>

                </li>

            </ul>

        </div>

    </div>

</nav>

<?php endif; ?>

<?php showFlash(); ?>

<main>
