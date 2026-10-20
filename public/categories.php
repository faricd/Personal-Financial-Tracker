
<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/language.php';

requireLogin();

$userId = (int) $_SESSION["user_id"];
$error = "";

$data = [
    'name' => '',
    'type' => 'expense',
    'icon' => 'bi-tag'
];

// CREATE: Add a new category
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    verifyCsrfToken();

    [$data, $error] = validateCategory(
        $conn,
        $userId,
        $_POST
    );

    if ($error === "") {

        $stmt = $conn->prepare(
            "INSERT INTO categories
             (user_id, name, type, icon)
             VALUES (?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "isss",
            $userId,
            $data['name'],
            $data['type'],
            $data['icon']
        );

        $stmt->execute();

        setFlash(
            "success",
            t('category_added_success')
            . ' "' . $data['name'] . '"'
        );

        header("Location: categories.php");
        exit;
    }
}

// READ: Categories and their transaction counts
$categories = fetchAll(
    $conn,
    "SELECT c.*,
            COUNT(t.id) AS used,
            COALESCE(SUM(t.amount), 0) AS total
     FROM categories c
     LEFT JOIN transactions t
        ON t.category_id = c.id
     WHERE c.user_id = ?
     GROUP BY c.id
     ORDER BY c.type, c.name",
    "i",
    [$userId]
);

// Group categories by type
$grouped = [
    'income' => [],
    'expense' => []
];

foreach ($categories as $category) {
    $grouped[$category['type']][] = $category;
}

// Page settings
$pageTitle = t('categories') . " | FinTrack";
$activePage = "categories";

// Form button already supports HTML icons
$submitLabel =
    '<i class="bi bi-plus-lg me-1"></i>'
    . e(t('add_category'));

include __DIR__ . '/../includes/header.php';

?>

<!-- Page Header -->
<div class="container page-header">

    <h1 class="h2 fw-bold mb-1">
        <?= e(t('categories')) ?>
    </h1>

    <p class="text-muted mb-0">
        <?= e(t('categories_description')) ?>
    </p>

</div>


<div class="container pb-4">

    <div class="row g-4">

        <!-- Add Category Form -->
        <div class="col-lg-4">

            <div class="card p-4">

                <h2 class="h5 fw-bold mb-3">
                    <?= e(t('add_category')) ?>
                </h2>

                <?php if ($error): ?>

                    <div class="alert alert-danger">
                        <?= e($error) ?>
                    </div>

                <?php endif; ?>

                <?php
                    include __DIR__ . '/../includes/category_form.php';
                ?>

            </div>

        </div>


        <!-- Category Lists -->
        <div class="col-lg-8">

            <div class="row g-4">

                <?php foreach (
                    ['income' => 'success', 'expense' => 'danger']
                    as $type => $color
                ): ?>

                    <div class="col-md-6">

                        <div class="card p-4 h-100">

                            <!-- Category Type Heading -->
                            <h2 class="h6 fw-bold mb-3 text-<?= $color ?>">

                                <?= e(t(
                                    $type === 'income'
                                        ? 'income_categories'
                                        : 'expense_categories'
                                )) ?>

                                <span class="badge text-bg-light">
                                    <?= count($grouped[$type]) ?>
                                </span>

                            </h2>

                            <!-- Empty State -->
                            <?php if (!$grouped[$type]): ?>

                                <div class="empty-state">

                                    <i class="bi bi-tags"></i>

                                    <p>
                                        <?= e(t(
                                            $type === 'income'
                                                ? 'no_income_categories'
                                                : 'no_expense_categories'
                                        )) ?>
                                    </p>

                                </div>

                            <?php endif; ?>


                            <!-- Categories -->
                            <?php foreach ($grouped[$type] as $category): ?>

                                <div class="category-item">

                                    <!-- Icon -->
                                    <span class="category-icon bg-<?= $color ?>-subtle text-<?= $color ?>">

                                        <i class="bi <?= e($category['icon']) ?>"></i>

                                    </span>

                                    <!-- Category Information -->
                                    <div class="flex-grow-1">

                                        <div class="fw-semibold">
                                            <?= e($category['name']) ?>
                                        </div>

                                        <div class="small text-muted">

                                            <?= (int) $category['used'] ?>

                                            <?= e(t('transactions')) ?>

                                            &middot;

                                            <?= money($category['total']) ?>

                                        </div>

                                    </div>


                                    <!-- Edit -->
                                    <a
                                        href="edit_category.php?id=<?= (int) $category['id'] ?>"
                                        class="btn btn-sm btn-light"
                                        title="<?= e(t('edit')) ?>"
                                        aria-label="<?= e(t('edit')) ?>">

                                        <i class="bi bi-pencil"></i>

                                    </a>


                                    <!-- Delete / Locked -->
                                    <?php if ((int) $category['used'] === 0): ?>

                                        <form
                                            method="POST"
                                            action="delete_category.php"
                                            class="d-inline delete-form">

                                            <?= csrfField() ?>

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int) $category['id'] ?>">

                                            <button
                                                type="button"
                                                class="btn btn-sm btn-light text-danger delete-btn"
                                                data-title="<?= e(t('delete_category_confirm') . ' "' . $category['name'] . '"?') ?>"
                                                title="<?= e(t('delete')) ?>"
                                                aria-label="<?= e(t('delete')) ?>">

                                                <i class="bi bi-trash"></i>

                                            </button>

                                        </form>

                                    <?php else: ?>

                                        <button
                                            type="button"
                                            class="btn btn-sm btn-light text-muted"
                                            disabled
                                            title="<?= e(t('category_in_use')) ?>">

                                            <i class="bi bi-lock"></i>

                                        </button>

                                    <?php endif; ?>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        </div>

    </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
