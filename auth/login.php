<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

// Start session
ensure_session_started();

// Redirect if already logged in
if (is_logged_in()) {
    header("Location: auth/index.php");
    exit;
}

// Check remember me cookie
if (check_remember_cookie($pdo)) {
    header("Location: ../index.php");
    exit;
}

$errors = [];
$username = '';

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = clean_input($_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) ? true : false;
    
    // Validate inputs
    if (empty($username)) {
        $errors['username'] = 'Username diperlukan';
    }
    
    if (empty($password)) {
        $errors['password'] = 'Password diperlukan';
    }
    
    // If no validation errors, check if user exists
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_anonymous = FALSE");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['is_anonymous'] = false;
            
            // Set remember me cookie if checked
            if ($remember) {
                set_remember_me_cookie($user['user_id']);
            }
            
            // Redirect to home page
            header("Location: ../index.php");
            exit;
        } else {
            $errors['login'] = 'Username atau password salah';
        }
    }
}

include '../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <h4 class="mb-0">Login ke SSIPFix</h4>
                    <p class="text-muted">Masukkan username dan password Anda</p>
                </div>
                
                <?php if (isset($errors['login'])): ?>
                    <div class="alert alert-danger">
                        <?php echo $errors['login']; ?>
                    </div>
                <?php endif; ?>
                
                <form action="login.php" method="post">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>" 
                               id="username" name="username" value="<?php echo htmlspecialchars($username); ?>">
                        <?php if (isset($errors['username'])): ?>
                            <div class="invalid-feedback">
                                <?php echo $errors['username']; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" 
                               id="password" name="password">
                        <?php if (isset($errors['password'])): ?>
                            <div class="invalid-feedback">
                                <?php echo $errors['password']; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember">
                        <label class="form-check-label" for="remember">Ingat saya selama 30 hari</label>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt me-2"></i> Login
                        </button>
                        <a href="anonymous.php" class="btn btn-outline-secondary">
                            <i class="fas fa-user-secret me-2"></i> Login sebagai Anonymous
                        </a>
                    </div>
                </form>
            </div>
            <div class="card-footer bg-light text-center py-3">
                <p class="mb-0">Belum punya akun? <a href="register.php">Daftar</a></p>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>