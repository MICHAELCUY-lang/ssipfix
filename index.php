<?php
require_once 'config/db.php';
require_once 'includes/functions.php';

// Start session
ensure_session_started();

// Check remember me cookie if not logged in
if (!is_logged_in()) {
    check_remember_cookie($pdo);
}

include 'includes/header.php';

// Redirect to login if not logged in
if (!is_logged_in()) {
    header("Location: auth/login.php");
    exit;
}

// Get posts with user information
$stmt = $pdo->query("
    SELECT p.*, u.username, u.profile_picture, u.is_anonymous, 
           (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id) as comment_count
    FROM posts p
    JOIN users u ON p.user_id = u.user_id
    ORDER BY p.created_at DESC
");
$posts = $stmt->fetchAll();
?>

<div class="row">
    <div class="col-md-8">
       <div class="card create-post-card">
    <div class="card-body">
        <?php if (isset($_SESSION['post_errors'])): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($_SESSION['post_errors'] as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php unset($_SESSION['post_errors']); ?>
        <?php endif; ?>
        
        <form action="posts/create.php" method="post" enctype="multipart/form-data">
            <div class="mb-3">
                <textarea name="content" class="form-control create-post-input count-chars" 
                          placeholder="Apa yang Anda pikirkan? (Opsional jika mengunggah foto/video)" rows="3" maxlength="500" data-counter="char-counter"><?php echo isset($_SESSION['post_content']) ? htmlspecialchars($_SESSION['post_content']) : ''; ?></textarea>
                <small id="char-counter" class="text-muted">500 karakter tersisa</small>
                <?php if (isset($_SESSION['post_content'])) { unset($_SESSION['post_content']); } ?>
            </div>
            
            <div class="media-preview" style="display: none;">
                <button type="button" class="remove-media-btn"><i class="fas fa-times"></i></button>
                <img class="media-preview-element" src="" alt="Preview">
            </div>
            
            <div class="d-flex justify-content-between align-items-center">
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
                <div>
                    <!-- Checkbox untuk post sebagai anonymous -->
                    <div class="form-check form-check-inline me-2">
                        <input class="form-check-input" type="checkbox" id="post_as_anonymous" name="post_as_anonymous" value="1">
                        <label class="form-check-label" for="post_as_anonymous">
                            <i class="fas fa-user-secret"></i> Posting sebagai Anonymous
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-1"></i> Posting
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
        <!-- Posts Feed -->
        <?php if (count($posts) > 0): ?>
            <?php foreach ($posts as $post): ?>
                <div class="card post-card">
                    <div class="card-header">
                        <div class="post-header">
                            <?php if ($post['is_anonymous']): ?>
                                <div class="anonymous-icon">
                                    <i class="fas fa-user-secret"></i>
                                </div>
                                <div class="post-user-info">
                                    <h6 class="mb-0 anonymous-user">Anonymous</h6>
                                    <small class="text-muted-small"><?php echo format_date($post['created_at']); ?></small>
                                </div>
                            <?php else: ?>
                                <img src="/ssipfix/assets/images/<?php echo $post['profile_picture']; ?>" 
                                     class="avatar-md rounded-circle" alt="Profile">
                                <div class="post-user-info">
                                    <h6 class="mb-0">
                                        <a href="/ssipfix/profile/index.php?id=<?php echo $post['user_id']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($post['username']); ?>
                                        </a>
                                    </h6>
                                    <small class="text-muted-small"><?php echo format_date($post['created_at']); ?></small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="post-content">
                            <p><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                        </div>
                        
                        <?php if ($post['media_type'] == 'photo'): ?>
                            <div class="post-media">
                                <img src="<?php echo $post['media_url']; ?>" alt="Post media" class="img-fluid">
                            </div>
                        <?php elseif ($post['media_type'] == 'video'): ?>
                            <div class="post-media">
                                <video src="<?php echo $post['media_url']; ?>" controls class="img-fluid"></video>
                            </div>
                        <?php endif; ?>
                        
                        <?php 
                        // Get user reaction if logged in
                        $user_reaction = null;
                        if (is_logged_in()) {
                            $user_reaction = get_user_reaction($pdo, $_SESSION['user_id'], $post['post_id']);
                        }
                        ?>
                        
                        <div class="post-actions">
                            <div>
                                <button class="post-action-btn like-btn <?php echo $user_reaction == 'like' ? 'active-like' : ''; ?>" 
                                        data-post-id="<?php echo $post['post_id']; ?>">
                                    <i class="fas fa-thumbs-up"></i> <span class="like-count"><?php echo $post['likes']; ?></span>
                                </button>
                                <button class="post-action-btn dislike-btn <?php echo $user_reaction == 'dislike' ? 'active-dislike' : ''; ?>" 
                                        data-post-id="<?php echo $post['post_id']; ?>">
                                    <i class="fas fa-thumbs-down"></i> <span class="dislike-count"><?php echo $post['dislikes']; ?></span>
                                </button>
                            </div>
                            <button class="post-action-btn comment-toggle-btn" data-post-id="<?php echo $post['post_id']; ?>">
                                <i class="fas fa-comment"></i> <?php echo $post['comment_count']; ?> Komentar
                            </button>
                        </div>
                        
                        <!-- Comment Form -->
<div class="comment-form mt-3" data-post-id="<?php echo $post['post_id']; ?>" style="display: none;">
    <form action="/ssipfix/comments/add.php" method="post">
        <input type="hidden" name="post_id" value="<?php echo $post['post_id']; ?>">
        <div class="input-group mb-2">
            <input type="text" name="content" class="form-control count-chars" 
                   placeholder="Tulis komentar..." maxlength="200" data-counter="comment-counter-<?php echo $post['post_id']; ?>">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
        <div class="d-flex justify-content-between align-items-center">
            <small id="comment-counter-<?php echo $post['post_id']; ?>" class="text-muted">200 karakter tersisa</small>
            <!-- Checkbox untuk komentar sebagai anonymous -->
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="comment_as_anonymous_<?php echo $post['post_id']; ?>" 
                       name="comment_as_anonymous" value="1">
                <label class="form-check-label" for="comment_as_anonymous_<?php echo $post['post_id']; ?>">
                    <i class="fas fa-user-secret"></i> Komentari sebagai Anonymous
                </label>
            </div>
        </div>
    </form>
</div>
                        <!-- Comments Section -->
                        <div class="comment-section" data-post-id="<?php echo $post['post_id']; ?>" style="display: none;">
                            <?php 
                            $comments = get_comments($pdo, $post['post_id']);
                            if (count($comments) > 0):
                            ?>
                                <?php foreach ($comments as $comment): ?>
                                    <div class="comment">
                                        <div class="comment-header">
                                            <?php if ($comment['is_anonymous']): ?>
                                                <div class="anonymous-icon" style="width: 30px; height: 30px; font-size: 0.9rem;">
                                                    <i class="fas fa-user-secret"></i>
                                                </div>
                                                <div class="comment-user-info">
                                                    <h6 class="mb-0 anonymous-user" style="font-size: 0.9rem;">Anonymous</h6>
                                                    <small class="text-muted-small"><?php echo format_date($comment['created_at']); ?></small>
                                                </div>
                                            <?php else: ?>
                                                <img src="/ssipfix/assets/images/<?php echo $comment['profile_picture']; ?>" 
                                                     class="avatar-sm rounded-circle" alt="Profile">
                                                <div class="comment-user-info">
                                                    <h6 class="mb-0" style="font-size: 0.9rem;">
                                                        <a href="/ssipfix/profile/index.php?id=<?php echo $comment['user_id']; ?>" class="text-decoration-none">
                                                            <?php echo htmlspecialchars($comment['username']); ?>
                                                        </a>
                                                    </h6>
                                                    <small class="text-muted-small"><?php echo format_date($comment['created_at']); ?></small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="comment-content">
                                            <p class="mb-0"><?php echo htmlspecialchars($comment['content']); ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-center text-muted my-3">Belum ada komentar.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-comment-slash fa-3x text-muted mb-3"></i>
                    <h5>Belum ada postingan</h5>
                    <p class="text-muted">Jadilah yang pertama membuat postingan!</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="col-md-4">
        <!-- User Card -->
        <div class="card mb-4">
            <div class="card-body text-center">
                <?php if ($current_user['is_anonymous']): ?>
                    <div class="anonymous-icon mx-auto mb-3" style="width: 80px; height: 80px; font-size: 2rem;">
                        <i class="fas fa-user-secret"></i>
                    </div>
                    <h5 class="anonymous-user">Anonymous User</h5>
                    <p class="text-muted small">Anda sedang menggunakan mode anonim</p>
                    <a href="auth/logout.php" class="btn btn-outline-danger btn-sm">
                        <i class="fas fa-sign-out-alt me-1"></i> Keluar
                    </a>
                <?php else: ?>
                    <img src="/ssipfix/assets/images/<?php echo $current_user['profile_picture']; ?>" 
                         class="avatar-lg rounded-circle mb-3" alt="Profile">
                    <h5><?php echo htmlspecialchars($current_user['username']); ?></h5>
                    <?php if ($current_user['bio']): ?>
                        <p class="small text-muted"><?php echo htmlspecialchars($current_user['bio']); ?></p>
                    <?php endif; ?>
                    <div class="d-grid gap-2">
                        <a href="profile/index.php?id=<?php echo $current_user['user_id']; ?>" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-user me-1"></i> Lihat Profil
                        </a>
                        <a href="profile/edit.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-cog me-1"></i> Edit Profil
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Tips & Info Card -->
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0"><i class="fas fa-info-circle me-2"></i> Tips Penggunaan</h5>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item px-0">
                        <i class="fas fa-check-circle text-success me-2"></i> 
                        Anda dapat mengunggah foto dan video dalam postingan
                    </li>
                    <li class="list-group-item px-0">
                        <i class="fas fa-check-circle text-success me-2"></i> 
                        Berikan like atau dislike pada postingan yang Anda sukai atau tidak sukai
                    </li>
                    <li class="list-group-item px-0">
                        <i class="fas fa-check-circle text-success me-2"></i> 
                        Fitur komentar memungkinkan Anda berinteraksi dengan pengguna lain
                    </li>
                    <li class="list-group-item px-0">
                        <i class="fas fa-check-circle text-success me-2"></i> 
                        Privasi terjaga dengan mode anonymous jika diperlukan
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>