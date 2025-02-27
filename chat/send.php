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

// Initialize media variables
$media_type = 'none';
$media_url = '';

// Validate inputs
if ($conversation_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid conversation ID']);
    exit;
}

// Check if a message, media, or voice note is provided
$has_photo = isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE;
$has_video = isset($_FILES['video']) && $_FILES['video']['error'] !== UPLOAD_ERR_NO_FILE;
$has_voice = isset($_POST['voice_data']) && !empty($_POST['voice_data']);

if (empty($message) && !$has_photo && !$has_video && !$has_voice) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Message, media, or voice note is required']);
    exit;
}

// Process media uploads
if ($has_photo && $has_video) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'You can only upload one media type per message']);
    exit;
}

if ($has_photo) {
    $media_type = 'photo';
    $upload_result = upload_media($_FILES['photo'], 'photo');
    
    if (!$upload_result['success']) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $upload_result['message']]);
        exit;
    }
    
    $media_url = $upload_result['file_path'];
}
elseif ($has_video) {
    $media_type = 'video';
    $upload_result = upload_media($_FILES['video'], 'video');
    
    if (!$upload_result['success']) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $upload_result['message']]);
        exit;
    }
    
    $media_url = $upload_result['file_path'];
}
elseif ($has_voice) {
    $media_type = 'voice';
    
    // Process voice data (base64)
    $voice_data = $_POST['voice_data'];
    
    // Decode base64 data
    if (preg_match('/^data:audio\/(\w+);base64,/', $voice_data, $matches)) {
        $audio_type = $matches[1];
        $data = substr($voice_data, strpos($voice_data, ',') + 1);
        $data = base64_decode($data);
        
        // Generate filename and path
        $filename = uniqid() . '.' . ($audio_type == 'webm' ? 'webm' : 'mp3');
        $upload_dir = '../assets/uploads/voice/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_path = $upload_dir . $filename;
        
        // Save the file
        if (file_put_contents($file_path, $data)) {
            $media_url = 'assets/uploads/voice/' . $filename;
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to save voice note']);
            exit;
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid voice data format']);
        exit;
    }
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
    
    // Insert the message with media if present
    $stmt = $pdo->prepare("
        INSERT INTO chat_messages (conversation_id, user_id, message, media_type, media_url)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$conversation_id, $user_id, $message, $media_type, $media_url]);
    
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