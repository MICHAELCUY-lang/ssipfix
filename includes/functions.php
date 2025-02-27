<?php
// Start session if not already started
function ensure_session_started() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
}

// Clean input data
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Check if user is logged in
function is_logged_in() {
    ensure_session_started();
    return isset($_SESSION['user_id']);
}

// Get current user info
function get_user_info($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

// Upload media function with improved error handling and logging
function upload_media($file, $type) {
    // Start logging information about the upload
    error_log("=== Starting media upload process ===");
    error_log("File details: " . json_encode([
        'name' => $file['name'],
        'type' => $file['type'],
        'size' => $file['size'],
        'tmp_name' => $file['tmp_name'],
        'error' => $file['error']
    ]));
    error_log("Upload type: " . $type);
    
    // Define target directory with full path
    $base_dir = dirname(dirname(__FILE__)); // Get the base directory path
    $target_dir = $base_dir . "/assets/uploads/";
    $target_dir .= ($type == 'photo') ? "photos/" : "videos/";
    error_log("Target directory: " . $target_dir);
    
    // Create directory if it doesn't exist
    if (!file_exists($target_dir)) {
        error_log("Directory doesn't exist, attempting to create: " . $target_dir);
        if (!mkdir($target_dir, 0777, true)) {
            error_log("ERROR: Failed to create directory");
            return ['success' => false, 'message' => 'Tidak dapat membuat direktori upload'];
        }
        // Make sure the directory has proper permissions
        chmod($target_dir, 0777);
        error_log("Directory created successfully with permissions 0777");
    }
    
    // Check if directory is writable
    if (!is_writable($target_dir)) {
        error_log("ERROR: Directory is not writable: " . $target_dir);
        chmod($target_dir, 0777); // Try to set write permissions
        
        if (!is_writable($target_dir)) {
            return ['success' => false, 'message' => 'Direktori upload tidak dapat ditulis'];
        }
    }
    
    // Check if file was actually uploaded
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE => 'Ukuran file melebihi batas upload_max_filesize di php.ini',
            UPLOAD_ERR_FORM_SIZE => 'Ukuran file melebihi batas MAX_FILE_SIZE yang ditentukan dalam form HTML',
            UPLOAD_ERR_PARTIAL => 'File hanya terupload sebagian',
            UPLOAD_ERR_NO_FILE => 'Tidak ada file yang diupload',
            UPLOAD_ERR_NO_TMP_DIR => 'Direktori temporary tidak ditemukan',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk',
            UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh ekstensi PHP'
        ];
        
        $error_message = isset($upload_errors[$file['error']]) 
            ? $upload_errors[$file['error']] 
            : 'Error upload tidak dikenal: ' . $file['error'];
        
        error_log("ERROR: " . $error_message);
        return ['success' => false, 'message' => $error_message];
    }
    
    // Get file extension and create new filename
    $file_extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $new_filename = uniqid() . '.' . $file_extension;
    $target_file = $target_dir . $new_filename;
    
    error_log("File extension: " . $file_extension);
    error_log("New filename: " . $new_filename);
    error_log("Target file path: " . $target_file);
    
    // Check file type
    if ($type == 'photo') {
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($file_extension, $allowed_types)) {
            error_log("ERROR: Invalid photo file type: " . $file_extension);
            return ['success' => false, 'message' => 'Hanya file JPG, JPEG, PNG & GIF yang diperbolehkan.'];
        }
    } else if ($type == 'video') {
        $allowed_types = ['mp4', 'webm', 'ogg', 'mov', 'avi'];
        if (!in_array($file_extension, $allowed_types)) {
            error_log("ERROR: Invalid video file type: " . $file_extension);
            return ['success' => false, 'message' => 'Hanya file MP4, WEBM, OGG, MOV & AVI yang diperbolehkan.'];
        }
    }
    
    // Note: We've removed the file size check to allow unlimited file sizes
    error_log("File size: " . $file["size"] . " bytes (no size limit enforced)");
    
    // Upload file
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        error_log("File successfully uploaded to: " . $target_file);
        
        // Verify the file was actually saved
        if (file_exists($target_file)) {
            error_log("File exists check: PASSED");
            $file_size = filesize($target_file);
            error_log("Saved file size: " . $file_size . " bytes");
            
            // Get file permissions
            $perms = substr(sprintf('%o', fileperms($target_file)), -4);
            error_log("File permissions: " . $perms);
            
            // Return file path without the base directory path
            $relative_path = str_replace($base_dir . '/', '', $target_file);
            error_log("Returning relative path: " . $relative_path);
            return ['success' => true, 'file_path' => $relative_path];
        } else {
            error_log("ERROR: File exists check FAILED. File was not saved properly.");
            return ['success' => false, 'message' => 'File berhasil diupload tetapi tidak tersimpan dengan benar.'];
        }
    } else {
        $php_error = error_get_last();
        error_log("ERROR: Failed to move uploaded file. PHP error: " . json_encode($php_error));
        return ['success' => false, 'message' => 'Terjadi kesalahan saat mengunggah file.'];
    }
}

// Format date
function format_date($date) {
    $timestamp = strtotime($date);
    $current_time = time();
    $diff = $current_time - $timestamp;
    
    if ($diff < 60) {
        return "Baru saja";
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . " menit yang lalu";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . " jam yang lalu";
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . " hari yang lalu";
    } else {
        return date("j F Y", $timestamp);
    }
}

// Get post data
function get_post($pdo, $post_id) {
    $stmt = $pdo->prepare("
        SELECT p.*, u.username, u.profile_picture, u.is_anonymous
        FROM posts p
        JOIN users u ON p.user_id = u.user_id
        WHERE p.post_id = ?
    ");
    $stmt->execute([$post_id]);
    return $stmt->fetch();
}

// Get comments for a post
function get_comments($pdo, $post_id) {
    $stmt = $pdo->prepare("
        SELECT c.*, u.username, u.profile_picture, u.is_anonymous
        FROM comments c
        JOIN users u ON c.user_id = u.user_id
        WHERE c.post_id = ?
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$post_id]);
    return $stmt->fetchAll();
}

// Check if user has reacted to post
function get_user_reaction($pdo, $user_id, $post_id) {
    $stmt = $pdo->prepare("
        SELECT type FROM reactions 
        WHERE user_id = ? AND post_id = ?
    ");
    $stmt->execute([$user_id, $post_id]);
    $result = $stmt->fetch();
    return $result ? $result['type'] : null;
}

// Set cookie for auto login
function set_remember_me_cookie($user_id) {
    $token = bin2hex(random_bytes(32));
    $expiry = time() + (30 * 24 * 60 * 60); // 30 days
    
    setcookie('remember_token', $token, $expiry, '/', '', false, true);
    setcookie('user_id', $user_id, $expiry, '/', '', false, true);
    
    // In a real application, you should store this token in the database
    // with the user_id to validate it later
    // For simplicity, we're just using the cookie here
}

// Check remember me cookie
function check_remember_cookie($pdo) {
    if (isset($_COOKIE['remember_token']) && isset($_COOKIE['user_id'])) {
        $user_id = $_COOKIE['user_id'];
        
        // In a real application, you would validate the token against the database
        // For simplicity, we're just using the cookie existence
        
        // Get user data
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Set session
            ensure_session_started();
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['is_anonymous'] = $user['is_anonymous'];
            
            return true;
        }
    }
    
    return false;
}
?>