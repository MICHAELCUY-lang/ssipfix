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

// Get current user ID
$user_id = $_SESSION['user_id'];

// Initialize variables
$search_query = '';
$users = [];

// Handle search
if (isset($_GET['query']) && !empty($_GET['query'])) {
    $search_query = clean_input($_GET['query']);
    
    // Search for users by username
    $stmt = $pdo->prepare("
        SELECT user_id, username, profile_picture, bio, created_at
        FROM users
        WHERE username LIKE ? AND is_anonymous = FALSE AND user_id != ?
        ORDER BY username ASC
        LIMIT 50
    ");
    $stmt->execute(['%' . $search_query . '%', $user_id]);
    $users = $stmt->fetchAll();
}

include '../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <div class="card mb-4">
            <div class="card-header">
                <h4><i class="fas fa-search me-2"></i> Cari Pengguna</h4>
            </div>
            <div class="card-body">
                <form action="search.php" method="get" class="mb-4">
                    <div class="input-group">
                        <input type="text" name="query" class="form-control form-control-lg" 
                               placeholder="Cari berdasarkan username..." value="<?php echo htmlspecialchars($search_query); ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i> Cari
                        </button>
                    </div>
                </form>
                
                <?php if (!empty($search_query)): ?>
                    <h5 class="mb-3">Hasil untuk "<?php echo htmlspecialchars($search_query); ?>"</h5>
                    
                    <?php if (count($users) > 0): ?>
                        <div class="row">
                            <?php foreach ($users as $u): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center mb-3">
                                                <img src="/ssipfix/assets/images/<?php echo $u['profile_picture']; ?>" 
                                                     class="avatar-lg rounded-circle me-3" alt="Profile">
                                                <div>
                                                    <h5 class="mb-1"><?php echo htmlspecialchars($u['username']); ?></h5>
                                                    <small class="text-muted">Bergabung <?php echo date('M Y', strtotime($u['created_at'])); ?></small>
                                                </div>
                                            </div>
                                            
                                            <?php if (!empty($u['bio'])): ?>
                                                <p class="text-muted small mb-3">
                                                    <?php echo (strlen($u['bio']) > 100) ? substr(htmlspecialchars($u['bio']), 0, 100) . '...' : htmlspecialchars($u['bio']); ?>
                                                </p>
                                            <?php endif; ?>
                                            
                                            <div class="d-flex justify-content-between mt-3">
                                                <a href="/ssipfix/profile/index.php?id=<?php echo $u['user_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-user me-1"></i> Profil
                                                </a>
                                                
                                                <?php if (!$_SESSION['is_anonymous']): ?>
                                                    <a href="/ssipfix/chat/new.php?recipient=<?php echo $u['user_id']; ?>" class="btn btn-sm btn-outline-success">
                                                        <i class="fas fa-comment me-1"></i> Pesan
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> Tidak ditemukan pengguna dengan username "<?php echo htmlspecialchars($search_query); ?>"
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-users fa-4x text-muted mb-3"></i>
                        <h4>Cari Pengguna</h4>
                        <p class="text-muted">Masukkan username untuk menemukan pengguna</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>