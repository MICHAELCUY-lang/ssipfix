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

// Check if anonymous users shouldn't have chat
if (isset($_SESSION['is_anonymous']) && $_SESSION['is_anonymous']) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Anonymous users cannot send messages']);
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get conversation ID and message
$conversation_id = isset($_POST['conversation_id']) ? intval($_POST['conversation_id']) : 0;
$message = isset($_POST['message']) ? clean_input($_POST['message']) : '';
$user_id = $_SESSION['user_id'];

// Validate inputs
if ($conversation_id <= 0 || empty($message)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

try {
    // Check if conversation exists and user is a participant
    $check_stmt = $pdo->prepare("
        SELECT conversation_id FROM chat_participants 
        WHERE conversation_id = ? AND user_id = ?
    ");
    $check_stmt->execute([$conversation_id, $user_id]);
    
    if ($check_stmt->rowCount() === 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Conversation not found or you are not a participant']);
        exit;
    }
    
    // Insert the message
    $stmt = $pdo->prepare("
        INSERT INTO chat_messages (conversation_id, user_id, message)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$conversation_id, $user_id, $message]);
    
    // Check if this is an AJAX request
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    } else {
        // Redirect back to the conversation
        header("Location: index.php?id=" . $conversation_id);
        exit;
    }
} catch (PDOException $e) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    } else {
        $_SESSION['error'] = 'Terjadi kesalahan saat mengirim pesan';
        header("Location: index.php?id=" . $conversation_id);
        exit;
    }
}
?>