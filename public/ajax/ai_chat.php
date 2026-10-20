
<?php
/*
 * FinTrack - FinBot AJAX Chat Endpoint
 * Supports English, Dari, and Pashto.
 *
 * Receives questions from JavaScript,
 * generates answers, and returns JSON.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/language.php';
require_once __DIR__ . '/../../includes/ai_helper.php';

// Return JSON with UTF-8 encoding
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache');

// User must be logged in and use POST
if (
    !isset($_SESSION['user_id']) ||
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {
    http_response_code(401);

    echo json_encode(
        [
            'answer' => t('chat_login_required')
        ],
        JSON_UNESCAPED_UNICODE
    );

    exit;
}

// Verify CSRF token
verifyCsrfToken();

// Get user information
$userId = (int) $_SESSION['user_id'];

$userType = $_SESSION['user_type'] ?? 'student';

// Get submitted question
$question = trim($_POST['question'] ?? '');

// Validate question
if (
    $question === '' ||
    mb_strlen($question) > 300
) {
    echo json_encode(
        [
            'answer' => t('chat_invalid_question')
        ],
        JSON_UNESCAPED_UNICODE
    );

    exit;
}

// Generate answer in the selected language
$answer = answerQuestion(
    $conn,
    $userId,
    $userType,
    $question
);

// Save the original question and generated answer
$stmt = $conn->prepare(
    "INSERT INTO ai_chat_history
     (user_id, question, answer)
     VALUES (?, ?, ?)"
);

$stmt->bind_param(
    "iss",
    $userId,
    $question,
    $answer
);

$stmt->execute();

// Return translated answer to JavaScript
echo json_encode(
    [
        'answer' => $answer
    ],
    JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
);

exit;
