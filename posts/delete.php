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

// Check if post ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: ../index.php");
    exit;
}

$post_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

try {
    // First check if post exists and belongs to the user
    // For an admin, you might modify this to allow deletion of any post
    $check_stmt = $pdo->prepare("SELECT media_type, media_url FROM posts WHERE post_id = ? AND user_id = ?");
    $check_stmt->execute([$post_id, $user_id]);
    $post = $check_stmt->fetch();
    
    if (!$post) {
        // Post not found or doesn't belong to the user
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            // AJAX request
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Post tidak ditemukan atau Anda tidak memiliki izin untuk menghapusnya']);
            exit;
        } else {
            $_SESSION['error'] = "Post tidak ditemukan atau Anda tidak memiliki izin untuk menghapusnya";
            header("Location: ../index.php");
            exit;
        }
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Delete all reactions associated with this post
    $stmt = $pdo->prepare("DELETE FROM reactions WHERE post_id = ?");
    $stmt->execute([$post_id]);
    
    // Delete all comments associated with this post
    $stmt = $pdo->prepare("DELETE FROM comments WHERE post_id = ?");
    $stmt->execute([$post_id]);
    
    // Delete the post
    $stmt = $pdo->prepare("DELETE FROM posts WHERE post_id = ?");
    $stmt->execute([$post_id]);
    
    // Delete associated media file if it exists
    if ($post['media_type'] != 'none' && !empty($post['media_url'])) {
        $file_path = '../' . $post['media_url']; // Add the relative path prefix
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Return success response
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        // AJAX request
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Post berhasil dihapus']);
        exit;
    } else {
        $_SESSION['success'] = "Post berhasil dihapus";
        header("Location: ../index.php");
        exit;
    }
} catch (PDOException $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        // AJAX request
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan saat menghapus post']);
        exit;
    } else {
        $_SESSION['error'] = "Terjadi kesalahan saat menghapus post";
        header("Location: ../index.php");
        exit;
    }
}
?>