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
$errors = [];

// Get post data
try {
    $stmt = $pdo->prepare("
        SELECT p.*, u.username, u.profile_picture, u.is_anonymous
        FROM posts p
        JOIN users u ON p.user_id = u.user_id
        WHERE p.post_id = ? AND p.user_id = ?
    ");
    $stmt->execute([$post_id, $user_id]);
    $post = $stmt->fetch();
    
    if (!$post) {
        // Post not found or doesn't belong to the user
        $_SESSION['error'] = "Post tidak ditemukan atau Anda tidak memiliki izin untuk mengeditnya";
        header("Location: ../index.php");
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Terjadi kesalahan. Silakan coba lagi.";
    header("Location: ../index.php");
    exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = isset($_POST['content']) ? clean_input($_POST['content']) : '';
    $media_type = $post['media_type'];
    $media_url = $post['media_url'];
    $remove_media = isset($_POST['remove_media']) ? true : false;
    
    // Validate content
    if (empty($content)) {
        $errors[] = "Konten tidak boleh kosong";
    } elseif (strlen($content) > 500) {
        $errors[] = "Konten maksimal 500 karakter";
    }
    
    // Handle media removal
    if ($remove_media && !empty($post['media_url'])) {
        $file_path = '../' . $post['media_url']; // Add the relative path prefix
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        $media_type = 'none';
        $media_url = '';
    }
    // Handle media update
    elseif (!$remove_media) {
        // Check if new photo is uploaded
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            // Delete old media if exists
            if (!empty($post['media_url'])) {
                $file_path = '../' . $post['media_url'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }
            
            $media_type = 'photo';
            $upload_result = upload_media($_FILES['photo'], 'photo');
            
            if (!$upload_result['success']) {
                $errors[] = $upload_result['message'];
            } else {
                $media_url = $upload_result['file_path'];
            }
        }
        // Check if new video is uploaded
        elseif (isset($_FILES['video']) && $_FILES['video']['error'] !== UPLOAD_ERR_NO_FILE) {
            // Delete old media if exists
            if (!empty($post['media_url'])) {
                $file_path = '../' . $post['media_url'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }
            
            $media_type = 'video';
            $upload_result = upload_media($_FILES['video'], 'video');
            
            if (!$upload_result['success']) {
                $errors[] = $upload_result['message'];
            } else {
                $media_url = $upload_result['file_path'];
            }
        }
    }
    
    // If no errors, update post in database
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE posts 
                SET content = ?, media_type = ?, media_url = ? 
                WHERE post_id = ? AND user_id = ?
            ");
            $stmt->execute([$content, $media_type, $media_url, $post_id, $user_id]);
            
            // Redirect to home page
            $_SESSION['success'] = "Post berhasil diperbarui";
            header("Location: ../index.php");
            exit;
        } catch (PDOException $e) {
            $errors[] = "Terjadi kesalahan. Silakan coba lagi.";
        }
    }
}

include '../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h4>Edit Post</h4>
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form action="edit.php?id=<?php echo $post_id; ?>" method="post" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="content" class="form-label">Konten</label>
                        <textarea name="content" id="content" class="form-control count-chars" 
                                  rows="4" maxlength="500" data-counter="char-counter"><?php echo htmlspecialchars($post['content']); ?></textarea>
                        <small id="char-counter" class="text-muted"><?php echo 500 - strlen($post['content']); ?> karakter tersisa</small>
                    </div>
                    
                    <?php if ($post['media_type'] != 'none' && !empty($post['media_url'])): ?>
                        <div class="mb-3">
                            <label class="form-label">Media Saat Ini</label>
                            <div class="post-media">
                                <?php if ($post['media_type'] == 'photo'): ?>
                                    <img src="/ssipfix/<?php echo $post['media_url']; ?>" alt="Post media" class="img-fluid">
                                <?php elseif ($post['media_type'] == 'video'): ?>
                                    <video src="/ssipfix/<?php echo $post['media_url']; ?>" controls class="img-fluid"></video>
                                <?php endif; ?>
                            </div>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" id="remove_media" name="remove_media">
                                <label class="form-check-label" for="remove_media">
                                    Hapus media ini
                                </label>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label class="form-label">Update Media (Opsional)</label>
                        <div class="media-upload-buttons">
                            <label class="media-upload-btn">
                                <i class="fas fa-image"></i> Foto
                                <input type="file" name="photo" class="file-input" style="display: none;" accept="image/*">
                            </label>
                            <label class="media-upload-btn">
                                <i class="fas fa-video"></i> Video
                                <input type="file" name="video" class="file-input" style="display: none;" accept="video/*">
                            </label>
                        </div>
                        
                        <div class="media-preview mt-3" style="display: none;">
                            <button type="button" class="remove-media-btn"><i class="fas fa-times"></i></button>
                            <img class="media-preview-element" src="" alt="Preview">
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="../index.php" class="btn btn-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>