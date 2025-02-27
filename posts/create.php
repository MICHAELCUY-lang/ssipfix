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

// Get user ID from session
$user_id = $_SESSION['user_id'];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = isset($_POST['content']) ? clean_input($_POST['content']) : '';
    $media_type = 'none';
    $media_url = '';
    $errors = [];
    
    // Check if posting as anonymous
    $post_as_anonymous = isset($_POST['post_as_anonymous']) && $_POST['post_as_anonymous'] == '1';
    
    // If posting as anonymous, get the anonymous user ID
    if ($post_as_anonymous) {
        $stmt = $pdo->query("SELECT user_id FROM users WHERE is_anonymous = TRUE LIMIT 1");
        $anonymous_user = $stmt->fetch();
        
        if ($anonymous_user) {
            $user_id = $anonymous_user['user_id'];
            error_log("Posting as anonymous user. User ID: " . $user_id);
        } else {
            error_log("Anonymous user not found. Using regular user.");
        }
    }

    // Debug logging
    error_log("=== Processing post creation ===");
    error_log("User ID: " . $user_id);
    error_log("Content length: " . strlen($content));
    error_log("Posting as anonymous: " . ($post_as_anonymous ? 'YES' : 'NO'));
    
    // Check file uploads
    $has_photo = isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE;
    $has_video = isset($_FILES['video']) && $_FILES['video']['error'] !== UPLOAD_ERR_NO_FILE;
    
    error_log("Has photo upload: " . ($has_photo ? 'YES' : 'NO'));
    error_log("Has video upload: " . ($has_video ? 'YES' : 'NO'));
    
    // Hapus validasi teks wajib jika ada media yang diupload
    if (empty($content) && !$has_photo && !$has_video) {
        $errors[] = "Anda harus mengisi konten atau mengunggah foto/video";
    } elseif (strlen($content) > 500) {
        $errors[] = "Konten maksimal 500 karakter";
    }
    
    if ($has_photo && $has_video) {
        $errors[] = "Anda hanya dapat mengunggah foto atau video, tidak keduanya";
        error_log("ERROR: Both photo and video uploaded");
    }

    // Check if photo is uploaded
    if ($has_photo) {
        error_log("Processing photo upload");
        $media_type = 'photo';
        $upload_result = upload_media($_FILES['photo'], 'photo');
        
        if (!$upload_result['success']) {
            $errors[] = $upload_result['message'];
            error_log("Photo upload failed: " . $upload_result['message']);
        } else {
            $media_url = $upload_result['file_path'];
            error_log("Photo upload successful: " . $media_url);
        }
    }
    // Check if video is uploaded
    elseif ($has_video) {
        error_log("Processing video upload");
        $media_type = 'video';
        $upload_result = upload_media($_FILES['video'], 'video');
        
        if (!$upload_result['success']) {
            $errors[] = $upload_result['message'];
            error_log("Video upload failed: " . $upload_result['message']);
        } else {
            $media_url = $upload_result['file_path'];
            error_log("Video upload successful: " . $media_url);
        }
    }

    // If no errors, insert post into database
    if (empty($errors)) {
        try {
            error_log("Inserting post into database");
            error_log("Media type: " . $media_type);
            error_log("Media URL: " . $media_url);
            
            $stmt = $pdo->prepare("
                INSERT INTO posts (user_id, content, media_type, media_url) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$user_id, $content, $media_type, $media_url]);
            
            $post_id = $pdo->lastInsertId();
            error_log("Post created successfully with ID: " . $post_id);
            
            // Redirect to home page
            header("Location: ../index.php");
            exit;
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $errors[] = "Terjadi kesalahan. Silakan coba lagi.";
        }
    }

    // If there are errors, store in session and redirect back
    if (!empty($errors)) {
        error_log("Errors found: " . json_encode($errors));
        $_SESSION['post_errors'] = $errors;
        $_SESSION['post_content'] = $content; // Save content for re-population
        header("Location: ../index.php");
        exit;
    }
} else {
    // If not POST request, redirect to home
    header("Location: ../index.php");
    exit;
}
?>