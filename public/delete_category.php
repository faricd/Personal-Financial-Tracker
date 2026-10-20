
<?php
/*
 * FinTrack - Delete Category
 * Supports English, Dari, and Pashto.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/language.php';

requireLogin();

// Only allow POST requests
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: categories.php");
    exit;
}

// Verify CSRF token
verifyCsrfToken();

$id = (int) ($_POST["id"] ?? 0);
$userId = (int) $_SESSION["user_id"];

// Check whether this category belongs to the user
$category = fetchOne(
    $conn,
    "SELECT id FROM categories
     WHERE id = ? AND user_id = ?",
    "ii",
    [$id, $userId]
);

if (!$category) {
    setFlash("danger", t('category_not_found'));
    header("Location: categories.php");
    exit;
}

// Check whether the category is used in transactions
$used = fetchOne(
    $conn,
    "SELECT COUNT(*) AS cnt
     FROM transactions
     WHERE category_id = ? AND user_id = ?",
    "ii",
    [$id, $userId]
)['cnt'];

// Do not delete categories already used by transactions
if ($used > 0) {

    setFlash(
        "danger",
        sprintf(t('category_cannot_delete'), $used)
    );

} else {

    // Delete the category
    $stmt = $conn->prepare(
        "DELETE FROM categories
         WHERE id = ? AND user_id = ?"
    );

    $stmt->bind_param("ii", $id, $userId);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        setFlash(
            "success",
            t('category_deleted_success')
        );
    } else {
        setFlash(
            "danger",
            t('category_not_found')
        );
    }
}

// Return to Categories page
header("Location: categories.php");
exit;
