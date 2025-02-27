<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

// Start session
ensure_session_started();

// Check if user is logged in
if (!is_logged_in()) {
    header("Location: ../auth/login.php");
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../index.php");
    exit;
}

// Get post ID and content
$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
$content = isset($_POST['content']) ? clean_input($_POST['content']) : '';

// Check if commenting as anonymous
$comment_as_anonymous = isset($_POST['comment_as_anonymous']) && $_POST['comment_as_anonymous'] == '1';

// Get user ID from session or anonymous user
$user_id = $_SESSION['user_id'];

// If commenting as anonymous, get the anonymous user ID
if ($comment_as_anonymous) {
    $stmt = $pdo->query("SELECT user_id FROM users WHERE is_anonymous = TRUE LIMIT 1");
    $anonymous_user = $stmt->fetch();
    
    if ($anonymous_user) {
        $user_id = $anonymous_user['user_id'];
        error_log("Commenting as anonymous user. User ID: " . $user_id);
    } else {
        error_log("Anonymous user not found. Using regular user.");
    }
}

// Validate inputs
if ($post_id <= 0 || empty($content)) {
    $_SESSION['error'] = "Invalid comment data";
    header("Location: ../index.php");
    exit;
}

// Check content length
if (strlen($content) > 200) {
    $_SESSION['error'] = "Comment must be less than 200 characters";
    header("Location: ../index.php");
    exit;
}

try {
    // Check if post exists
    $check_stmt = $pdo->prepare("SELECT post_id FROM posts WHERE post_id = ?");
    $check_stmt->execute([$post_id]);
    
    if ($check_stmt->rowCount() === 0) {
        $_SESSION['error'] = "Post not found";
        header("Location: ../index.php");
        exit;
    }
    
    // Insert comment
    $stmt = $pdo->prepare("INSERT INTO comments (post_id, user_id, content) VALUES (?, ?, ?)");
    $stmt->execute([$post_id, $user_id, $content]);
    
    // Redirect back to index with the specific post's comment section open
    header("Location: ../index.php#post-" . $post_id);
    exit;
} catch (PDOException $e) {
    error_log("Error adding comment: " . $e->getMessage());
    $_SESSION['error'] = "Error adding comment";
    header("Location: ../index.php");
    exit;
}
?>