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

// Get post with user info
try {
    $stmt = $pdo->prepare("
        SELECT p.*, u.username, u.profile_picture, u.is_anonymous,
               (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id) as comment_count
        FROM posts p
        JOIN users u ON p.user_id = u.user_id
        WHERE p.post_id = ?
    ");
    $stmt->execute([$post_id]);
    $post = $stmt->fetch();
    
    if (!$post) {
        $_SESSION['error'] = "Post tidak ditemukan";
        header("Location: ../index.php");
        exit;
    }
    
    // Get comments for this post
    $comments = get_comments($pdo, $post_id);
    
    // Get user reaction if logged in
    $user_reaction = null;
    if (is_logged_in()) {
        $user_reaction = get_user_reaction($pdo, $_SESSION['user_id'], $post_id);
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Terjadi kesalahan. Silakan coba lagi.";
    header("Location: ../index.php");
    exit;
}

include '../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card post-card" id="post-<?php echo $post['post_id']; ?>">
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
                    
                    <?php if ($post['user_id'] == $_SESSION['user_id']): ?>
                        <div class="ms-auto dropdown">
                            <button class="btn btn-sm btn-link text-muted" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="/ssipfix/posts/edit.php?id=<?php echo $post['post_id']; ?>">
                                        <i class="fas fa-edit me-2"></i> Edit
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item text-danger" href="/ssipfix/posts/delete.php?id=<?php echo $post['post_id']; ?>" 
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus post ini?');">
                                        <i class="fas fa-trash-alt me-2"></i> Hapus
                                    </a>
                                </li>
                            </ul>
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
                        <img src="/ssipfix/<?php echo $post['media_url']; ?>" alt="Post media" class="img-fluid">
                    </div>
                <?php elseif ($post['media_type'] == 'video'): ?>
                    <div class="post-media">
                        <video src="/ssipfix/<?php echo $post['media_url']; ?>" controls class="img-fluid"></video>
                    </div>
                <?php endif; ?>
                
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
                    <button class="post-action-btn" id="comment-count">
                        <i class="fas fa-comment"></i> <?php echo $post['comment_count']; ?> Komentar
                    </button>
                </div>
                
                <!-- Comment Form -->
                <div class="comment-form mt-3">
                    <form action="/ssipfix/comments/add.php" method="post">
                        <input type="hidden" name="post_id" value="<?php echo $post['post_id']; ?>">
                        <div class="input-group">
                            <input type="text" name="content" class="form-control count-chars" 
                                   placeholder="Tulis komentar..." maxlength="200" data-counter="comment-counter">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                        <small id="comment-counter" class="text-muted">200 karakter tersisa</small>
                    </form>
                </div>
                
                <!-- Comments Section -->
                <div class="comment-section mt-4">
                    <h5 class="mb-3">Komentar (<?php echo count($comments); ?>)</h5>
                    
                    <?php if (count($comments) > 0): ?>
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
                                    
                                    <?php if ($comment['user_id'] == $_SESSION['user_id']): ?>
                                        <div class="ms-auto">
                                            <a href="/ssipfix/comments/delete.php?id=<?php echo $comment['comment_id']; ?>" 
                                               class="text-danger" onclick="return confirm('Hapus komentar ini?');">
                                                <i class="fas fa-times"></i>
                                            </a>
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
            <div class="card-footer">
                <a href="/ssipfix/index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Kembali
                </a>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>