<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$userId = (int) $_SESSION["user_id"];
$id     = (int) ($_GET["id"] ?? 0);
$error  = "";

$data = fetchOne($conn, "SELECT * FROM categories WHERE id = ? AND user_id = ?", "ii", [$id, $userId]);
if (!$data) {
    setFlash("danger", "Category not found.");
    header("Location: categories.php");
    exit;
}

// Is this category used? Then its type cannot change (it would break the transactions)
$used = fetchOne($conn, "SELECT COUNT(*) AS cnt FROM transactions WHERE category_id = ?", "i", [$id])['cnt'];

// UPDATE the category
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verifyCsrfToken();

    $oldType = $data['type'];
    [$data, $error] = validateCategory($conn, $userId, $_POST, $id);

    if ($error === "" && $used > 0 && $data['type'] !== $oldType) {
        $error = "This category is used by $used transactions, so its type cannot be changed.";
    }

    if ($error === "") {
        $stmt = $conn->prepare("UPDATE categories SET name = ?, type = ?, icon = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param("sssii", $data['name'], $data['type'], $data['icon'], $id, $userId);
        $stmt->execute();

        setFlash("success", "Category updated.");
        header("Location: categories.php");
        exit;
    }
}

$pageTitle   = "Edit Category | FinTrack";
$activePage  = "categories";
$submitLabel = '<i class="bi bi-check2 me-1"></i>Update Category';
include __DIR__ . '/../includes/header.php';
?>

<div class="container page-header">
    <a href="categories.php" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Back to categories</a>
    <h1 class="h2 fw-bold mt-2">Edit Category</h1>
</div>

<div class="container pb-4">
    <div class="card p-4 p-md-5 mx-auto" style="max-width: 520px">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>
        <?php if ($used > 0): ?>
            <div class="alert alert-info small"><i class="bi bi-info-circle me-1"></i>Used by <?= $used ?> transactions.</div>
        <?php endif; ?>
        <?php include __DIR__ . '/../includes/category_form.php'; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
