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

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get post ID and action
$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

// Validate inputs
if ($post_id <= 0 || !in_array($action, ['like', 'dislike'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

// Get current user ID
$user_id = $_SESSION['user_id'];

try {
    // Check if post exists
    $check_stmt = $pdo->prepare("SELECT post_id FROM posts WHERE post_id = ?");
    $check_stmt->execute([$post_id]);
    if ($check_stmt->rowCount() === 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Post not found']);
        exit;
    }

    // Check if user already reacted to this post
    $stmt = $pdo->prepare("SELECT type FROM reactions WHERE user_id = ? AND post_id = ?");
    $stmt->execute([$user_id, $post_id]);
    $current_reaction = $stmt->fetch();

    // Begin transaction
    $pdo->beginTransaction();

    if ($current_reaction) {
        $current_type = $current_reaction['type'];

        if ($current_type === $action) {
            // Remove reaction if clicking the same button again
            $stmt = $pdo->prepare("DELETE FROM reactions WHERE user_id = ? AND post_id = ?");
            $stmt->execute([$user_id, $post_id]);

            // Update post counts
            if ($action === 'like') {
                $stmt = $pdo->prepare("UPDATE posts SET likes = likes - 1 WHERE post_id = ?");
            } else {
                $stmt = $pdo->prepare("UPDATE posts SET dislikes = dislikes - 1 WHERE post_id = ?");
            }
            $stmt->execute([$post_id]);
        } else {
            // Change reaction type
            $stmt = $pdo->prepare("UPDATE reactions SET type = ? WHERE user_id = ? AND post_id = ?");
            $stmt->execute([$action, $user_id, $post_id]);

            // Update post counts
            if ($action === 'like') {
                $stmt = $pdo->prepare("UPDATE posts SET likes = likes + 1, dislikes = dislikes - 1 WHERE post_id = ?");
            } else {
                $stmt = $pdo->prepare("UPDATE posts SET likes = likes - 1, dislikes = dislikes + 1 WHERE post_id = ?");
            }
            $stmt->execute([$post_id]);
        }
    } else {
        // Add new reaction
        $stmt = $pdo->prepare("INSERT INTO reactions (user_id, post_id, type) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $post_id, $action]);

        // Update post counts
        if ($action === 'like') {
            $stmt = $pdo->prepare("UPDATE posts SET likes = likes + 1 WHERE post_id = ?");
        } else {
            $stmt = $pdo->prepare("UPDATE posts SET dislikes = dislikes + 1 WHERE post_id = ?");
        }
        $stmt->execute([$post_id]);
    }

    // Commit transaction
    $pdo->commit();

    // Get updated counts
    $stmt = $pdo->prepare("SELECT likes, dislikes FROM posts WHERE post_id = ?");
    $stmt->execute([$post_id]);
    $post = $stmt->fetch();

    // Get user's current reaction
    $stmt = $pdo->prepare("SELECT type FROM reactions WHERE user_id = ? AND post_id = ?");
    $stmt->execute([$user_id, $post_id]);
    $new_reaction = $stmt->fetch();

    // Prepare response
    $response = [
        'success' => true,
        'likes' => $post['likes'],
        'dislikes' => $post['dislikes'],
        'userLiked' => $new_reaction && $new_reaction['type'] === 'like',
        'userDisliked' => $new_reaction && $new_reaction['type'] === 'dislike'
    ];

    header('Content-Type: application/json');
    echo json_encode($response);
} catch (PDOException $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>