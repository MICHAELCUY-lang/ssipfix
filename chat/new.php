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

// Check if anonymous users shouldn't have chat
if (isset($_SESSION['is_anonymous']) && $_SESSION['is_anonymous']) {
    header("Location: ../index.php");
    exit;
}

// Get current user ID
$user_id = $_SESSION['user_id'];

// Get all users except current user and anonymous
$stmt = $pdo->prepare("
    SELECT user_id, username, profile_picture
    FROM users
    WHERE user_id != ? AND is_anonymous = FALSE
    ORDER BY username ASC
");
$stmt->execute([$user_id]);
$users = $stmt->fetchAll();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['recipient_id']) && is_numeric($_POST['recipient_id'])) {
        $recipient_id = intval($_POST['recipient_id']);
        $message = isset($_POST['message']) ? clean_input($_POST['message']) : '';
        
        // Initialize media variables
        $media_type = 'none';
        $media_url = '';
        
        // Check if a message or media is provided
        $has_photo = isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE;
        $has_video = isset($_FILES['video']) && $_FILES['video']['error'] !== UPLOAD_ERR_NO_FILE;
        
        if (empty($message) && !$has_photo && !$has_video) {
            $_SESSION['error'] = 'Pesan atau media diperlukan';
            header("Location: new.php");
            exit;
        }
        
        // Process media uploads
        if ($has_photo && $has_video) {
            $_SESSION['error'] = 'Anda hanya dapat mengunggah satu jenis media per pesan';
            header("Location: new.php");
            exit;
        }
        
        if ($has_photo) {
            $media_type = 'photo';
            $upload_result = upload_media($_FILES['photo'], 'photo');
            
            if (!$upload_result['success']) {
                $_SESSION['error'] = $upload_result['message'];
                header("Location: new.php");
                exit;
            }
            
            $media_url = $upload_result['file_path'];
        }
        elseif ($has_video) {
            $media_type = 'video';
            $upload_result = upload_media($_FILES['video'], 'video');
            
            if (!$upload_result['success']) {
                $_SESSION['error'] = $upload_result['message'];
                header("Location: new.php");
                exit;
            }
            
            $media_url = $upload_result['file_path'];
        }
        
        // Check if recipient exists
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE user_id = ? AND is_anonymous = FALSE");
        $stmt->execute([$recipient_id]);
        
        if ($stmt->rowCount() === 0) {
            $_SESSION['error'] = 'Pengguna tidak ditemukan';
            header("Location: new.php");
            exit;
        }
        
        // Check if conversation already exists
        $stmt = $pdo->prepare("
            SELECT c.conversation_id
            FROM chat_conversations c
            JOIN chat_participants p1 ON c.conversation_id = p1.conversation_id AND p1.user_id = ?
            JOIN chat_participants p2 ON c.conversation_id = p2.conversation_id AND p2.user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$user_id, $recipient_id]);
        $existing_conversation = $stmt->fetch();
        
        try {
            $pdo->beginTransaction();
            
            if ($existing_conversation) {
                $conversation_id = $existing_conversation['conversation_id'];
            } else {
                // Create new conversation
                $stmt = $pdo->query("INSERT INTO chat_conversations () VALUES ()");
                $conversation_id = $pdo->lastInsertId();
                
                // Add participants
                $stmt = $pdo->prepare("INSERT INTO chat_participants (conversation_id, user_id) VALUES (?, ?)");
                $stmt->execute([$conversation_id, $user_id]);
                $stmt->execute([$conversation_id, $recipient_id]);
            }
            
            // Add message with media if present
            $stmt = $pdo->prepare("
                INSERT INTO chat_messages (conversation_id, user_id, message, media_type, media_url)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$conversation_id, $user_id, $message, $media_type, $media_url]);
            
            $pdo->commit();
            
            // Redirect to the conversation
            header("Location: index.php?id=" . $conversation_id);
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['error'] = 'Terjadi kesalahan: ' . $e->getMessage();
            header("Location: new.php");
            exit;
        }
    }
}

include '../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Mulai Percakapan Baru</h5>
                <a href="index.php" class="btn btn-sm btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
            <div class="card-body">
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger">
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <div class="mb-4">
                    <div class="input-group">
                        <input type="text" id="search-user" class="form-control" placeholder="Cari pengguna...">
                        <button class="btn btn-outline-secondary" type="button">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>

                <div id="users-list">
                    <?php if (count($users) > 0): ?>
                        <div class="list-group">
                            <?php foreach ($users as $u): ?>
                                <a href="#" class="list-group-item list-group-item-action user-item" data-user-id="<?php echo $u['user_id']; ?>" data-username="<?php echo htmlspecialchars($u['username']); ?>">
                                    <div class="d-flex align-items-center">
                                        <img src="/ssipfix/assets/images/<?php echo $u['profile_picture']; ?>" 
                                             class="avatar-md rounded-circle me-3" alt="Profile">
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($u['username']); ?></h6>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <p class="text-muted">Tidak ada pengguna yang tersedia</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal for composing message -->
<div class="modal fade" id="messageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Kirim Pesan ke <span id="recipient-name"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="new.php" method="post" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="recipient_id" id="recipient-id">
                    
                    <div class="media-preview mb-3" style="display: none;">
                        <button type="button" class="remove-media-btn"><i class="fas fa-times"></i></button>
                        <div class="media-preview-element"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="message" class="form-label">Pesan</label>
                        <textarea name="message" id="message" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Media (Opsional)</label>
                        <div class="d-flex gap-2">
                            <label class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-image me-1"></i> Tambah Foto
                                <input type="file" name="photo" class="file-input" style="display: none;" accept="image/*">
                            </label>
                            <label class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-video me-1"></i> Tambah Video
                                <input type="file" name="video" class="file-input" style="display: none;" accept="video/*">
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Kirim</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize modal
        const messageModal = new bootstrap.Modal(document.getElementById('messageModal'));
        
        // Handle user selection
        const userItems = document.querySelectorAll('.user-item');
        userItems.forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                const userId = this.getAttribute('data-user-id');
                const username = this.getAttribute('data-username');
                
                document.getElementById('recipient-id').value = userId;
                document.getElementById('recipient-name').textContent = username;
                
                // Clear any previous media
                const mediaPreview = document.querySelector('.media-preview');
                mediaPreview.style.display = 'none';
                const mediaElement = document.querySelector('.media-preview-element');
                while (mediaElement.firstChild) {
                    mediaElement.removeChild(mediaElement.firstChild);
                }
                
                // Reset file inputs
                const fileInputs = document.querySelectorAll('.file-input');
                fileInputs.forEach(input => {
                    input.value = '';
                    input.disabled = false;
                });
                
                // Clear message textarea
                document.getElementById('message').value = '';
                
                messageModal.show();
            });
        });
        
        // Handle user search
        const searchInput = document.getElementById('search-user');
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const userItems = document.querySelectorAll('.user-item');
            
            userItems.forEach(item => {
                const username = item.querySelector('h6').textContent.toLowerCase();
                if (username.includes(searchTerm)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });
        
        // Handle file input change for preview
        const fileInputs = document.querySelectorAll(".file-input");
        fileInputs.forEach((input) => {
            input.addEventListener("change", function() {
                const previewContainer = document.querySelector(".media-preview");
                const previewElement = document.querySelector(".media-preview-element");
                
                // Clear any existing content
                while (previewElement.firstChild) {
                    previewElement.removeChild(previewElement.firstChild);
                }
                
                // Disable other file inputs when one is selected
                const photoInput = document.querySelector("input[name='photo']");
                const videoInput = document.querySelector("input[name='video']");
                
                if (this.name === "photo") {
                    videoInput.disabled = true;
                } else if (this.name === "video") {
                    photoInput.disabled = true;
                }
                
                if (this.files && this.files[0]) {
                    const file = this.files[0];
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        previewContainer.style.display = "block";
                        
                        if (file.type.startsWith("image/")) {
                            const img = document.createElement("img");
                            img.src = e.target.result;
                            img.className = "img-fluid rounded";
                            previewElement.appendChild(img);
                        } else if (file.type.startsWith("video/")) {
                            const video = document.createElement("video");
                            video.src = e.target.result;
                            video.controls = true;
                            video.className = "img-fluid rounded";
                            previewElement.appendChild(video);
                        }
                    };
                    
                    reader.readAsDataURL(file);
                }
            });
        });
        
        // Handle remove media button
        const removeMediaBtn = document.querySelector(".remove-media-btn");
        if (removeMediaBtn) {
            removeMediaBtn.addEventListener("click", function() {
                const previewContainer = document.querySelector(".media-preview");
                const previewElement = document.querySelector(".media-preview-element");
                const fileInputs = document.querySelectorAll(".file-input");
                
                // Clear preview element
                while (previewElement.firstChild) {
                    previewElement.removeChild(previewElement.firstChild);
                }
                
                previewContainer.style.display = "none";
                fileInputs.forEach((input) => {
                    input.value = "";
                    input.disabled = false; // Re-enable all inputs
                });
            });
        }
    });
</script>

<?php include '../includes/footer.php'; ?>