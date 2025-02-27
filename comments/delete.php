<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

// Start session
ensure_session_started();

// Check if user is logged in
if (!is_logged_in()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Check if ID is provided in the URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid comment ID']);
    exit;
}

$comment_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

try {
    // Check if comment exists and belongs to the user
    $check_stmt = $pdo->prepare("
        SELECT c.comment_id, c.post_id 
        FROM comments c
        WHERE c.comment_id = ? AND c.user_id = ?
    ");
    $check_stmt->execute([$comment_id, $user_id]);
    $comment = $check_stmt->fetch();
    
    if (!$comment) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Comment not found or you do not have permission to delete it']);
        exit;
    }
    
    // Store the post_id to redirect back to
    $post_id = $comment['post_id'];
    
    // Delete the comment
    $stmt = $pdo->prepare("DELETE FROM comments WHERE comment_id = ?");
    $stmt->execute([$comment_id]);
    
    // Check if deletion was successful
    if ($stmt->rowCount() > 0) {
        // If AJAX request, return JSON
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }
        
        // Otherwise, redirect back to post
        header("Location: ../index.php#post-" . $post_id);
        exit;
    } else {
        // If AJAX request, return JSON
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to delete comment']);
            exit;
        }
        
        $_SESSION['error'] = "Failed to delete comment";
        header("Location: ../index.php");
        exit;
    }
} catch (PDOException $e) {
    // If AJAX request, return JSON
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }
    
    $_SESSION['error'] = "Error processing your request";
    header("Location: ../index.php");
    exit;
}
?>