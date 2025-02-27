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

// Check if user ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: ../index.php");
    exit;
}

$profile_id = intval($_GET['id']);

// Get profile user info
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ? AND is_anonymous = FALSE");
    $stmt->execute([$profile_id]);
    $profile_user = $stmt->fetch();
    
    if (!$profile_user) {
        // User not found or is anonymous
        header("Location: ../index.php");
        exit;
    }
    
    // Get user's posts
    $stmt = $pdo->prepare("
        SELECT p.*, 
            (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id) as comment_count
        FROM posts p
        WHERE p.user_id = ?
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$profile_id]);
    $posts = $stmt->fetchAll();
    
    // Get post stats
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_posts,
            COALESCE(SUM(likes), 0) as total_likes,
            COALESCE(SUM(dislikes), 0) as total_dislikes
        FROM posts
        WHERE user_id = ?
    ");
    $stmt->execute([$profile_id]);
    $stats = $stmt->fetch();
    
    // Get comment count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_comments
        FROM comments
        WHERE user_id = ?
    ");
    $stmt->execute([$profile_id]);
    $comment_stats = $stmt->fetch();
} catch (PDOException $e) {
    $_SESSION['error'] = "Error retrieving profile data";
    header("Location: ../index.php");
    exit;
}

include '../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <!-- Profile Header -->
        <div class="profile-header">
            <img src="/ssipfix/assets/images/<?php echo $profile_user['profile_picture']; ?>" alt="Profile Picture" class="profile-picture">
            <div class="profile-info">
                <h3 class="mb-2"><?php echo htmlspecialchars($profile_user['username']); ?></h3>
                <?php if (!empty($profile_user['bio'])): ?>
                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($profile_user['bio'])); ?></p>
                <?php endif; ?>
                <small class="text-muted">Member since <?php echo date('F Y', strtotime($profile_user['created_at'])); ?></small>
            </div>
            
            <div class="profile-stats">
                <div class="stat-item">
                    <div class="stat-value"><?php echo $stats['total_posts']; ?></div>
                    <div class="stat-label">Posts</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $comment_stats['total_comments']; ?></div>
                    <div class="stat-label">Comments</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $stats['total_likes']; ?></div>
                    <div class="stat-label">Likes Received</div>
                </div>
            </div>
            
            <?php if ($profile_id == $_SESSION['user_id']): ?>
                <div class="mt-3">
                    <a href="edit.php" class="btn btn-outline-primary">
                        <i class="fas fa-edit me-1"></i> Edit Profile
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <h4 class="mb-4"><i class="fas fa-stream me-2"></i> Posts by <?php echo htmlspecialchars($profile_user['username']); ?></h4>
        
        <?php if (count($posts) > 0): ?>
            <?php foreach ($posts as $post): ?>
                <div class="card post-card" id="post-<?php echo $post['post_id']; ?>">
                    <div class="card-header">
                        <div class="post-header">
                            <img src="/ssipfix/assets/images/<?php echo $profile_user['profile_picture']; ?>" 
                                 class="avatar-md rounded-circle" alt="Profile">
                            <div class="post-user-info">
                                <h6 class="mb-0"><?php echo htmlspecialchars($profile_user['username']); ?></h6>
                                <small class="text-muted-small"><?php echo format_date($post['created_at']); ?></small>
                            </div>
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
                            <form action="../comments/add.php" method="post">
                                <input type="hidden" name="post_id" value="<?php echo $post['post_id']; ?>">
                                <div class="input-group">
                                    <input type="text" name="content" class="form-control count-chars" 
                                           placeholder="Tulis komentar..." maxlength="200" data-counter="comment-counter-<?php echo $post['post_id']; ?>">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                </div>
                                <small id="comment-counter-<?php echo $post['post_id']; ?>" class="text-muted">200 karakter tersisa</small>
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
                                                        <a href="../profile/index.php?id=<?php echo $comment['user_id']; ?>" class="text-decoration-none">
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
                    <p class="text-muted">Pengguna ini belum membuat postingan.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="col-md-4">
        <!-- Profile Stats Card -->
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0"><i class="fas fa-chart-bar me-2"></i> Statistik Aktivitas</h5>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Total Posts
                        <span class="badge bg-primary rounded-pill"><?php echo $stats['total_posts']; ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Total Comments
                        <span class="badge bg-primary rounded-pill"><?php echo $comment_stats['total_comments']; ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Likes Received
                        <span class="badge bg-success rounded-pill"><?php echo $stats['total_likes']; ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Dislikes Received
                        <span class="badge bg-danger rounded-pill"><?php echo $stats['total_dislikes']; ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Popularity Ratio
                        <span class="badge bg-info rounded-pill">
                            <?php 
                            $total_reactions = $stats['total_likes'] + $stats['total_dislikes'];
                            echo $total_reactions > 0 
                                ? round(($stats['total_likes'] / $total_reactions) * 100) . '%' 
                                : 'N/A'; 
                            ?>
                        </span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>