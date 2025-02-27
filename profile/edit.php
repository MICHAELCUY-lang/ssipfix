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

// Check if user is anonymous
if (isset($_SESSION['is_anonymous']) && $_SESSION['is_anonymous']) {
    header("Location: ../index.php");
    exit;
}

// Get user info
$user_id = $_SESSION['user_id'];
$user = get_user_info($pdo, $user_id);

// Initialize variables
$errors = [];
$success_message = '';
$username = $user['username'];
$email = $user['email'];
$bio = $user['bio'] ?? '';
$current_profile_picture = $user['profile_picture'];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update profile picture
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        $file = $_FILES['profile_picture'];
        $file_extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
        
        // Validate file type
        if (!in_array($file_extension, $allowed_types)) {
            $errors['profile_picture'] = 'Only JPG, JPEG, PNG & GIF files are allowed.';
        } 
        // Validate file size (limit to 5MB)
        else if ($file["size"] > 5 * 1024 * 1024) {
            $errors['profile_picture'] = 'File size must be less than 5MB.';
        } 
        else {
            // Upload file
            $target_dir = "../assets/images/";
            $new_filename = uniqid() . '.' . $file_extension;
            $target_file = $target_dir . $new_filename;
            
            if (move_uploaded_file($file["tmp_name"], $target_file)) {
                // Delete old profile picture if not default
                if ($current_profile_picture !== 'default.jpg' && file_exists($target_dir . $current_profile_picture)) {
                    unlink($target_dir . $current_profile_picture);
                }
                
                // Update profile picture in database
                $stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE user_id = ?");
                $stmt->execute([$new_filename, $user_id]);
                $current_profile_picture = $new_filename;
            } else {
                $errors['profile_picture'] = 'Error uploading file.';
            }
        }
    }
    
    // Update bio
    $bio = clean_input($_POST['bio'] ?? '');
    if (strlen($bio) > 500) {
        $errors['bio'] = 'Bio must be less than 500 characters.';
    } else {
        $stmt = $pdo->prepare("UPDATE users SET bio = ? WHERE user_id = ?");
        $stmt->execute([$bio, $user_id]);
    }
    
    // Update email
    $email = clean_input($_POST['email'] ?? '');
    if (empty($email)) {
        $errors['email'] = 'Email is required.';
    } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format.';
    } else {
        // Check if email already exists and is not the current user's email
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $stmt->execute([$email, $user_id]);
        
        if ($stmt->rowCount() > 0) {
            $errors['email'] = 'Email already in use.';
        } else {
            $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE user_id = ?");
            $stmt->execute([$email, $user_id]);
        }
    }
    
    // Update password if provided
    if (!empty($_POST['current_password']) && !empty($_POST['new_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Verify current password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch();
        
        if (!password_verify($current_password, $user_data['password'])) {
            $errors['current_password'] = 'Current password is incorrect.';
        } else if (strlen($new_password) < 6) {
            $errors['new_password'] = 'Password must be at least 6 characters.';
        } else if ($new_password !== $confirm_password) {
            $errors['confirm_password'] = 'Passwords do not match.';
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->execute([$hashed_password, $user_id]);
        }
    }
    
    // Set success message if no errors
    if (empty($errors)) {
        $success_message = 'Profile updated successfully!';
    }
}

include '../includes/header.php';
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title mb-0">Edit Profile</h3>
            </div>
            <div class="card-body">
                <?php if ($success_message): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                
                <form action="edit.php" method="post" enctype="multipart/form-data">
                    <div class="row mb-4">
                        <div class="col-md-4 text-center">
                            <img src="/ssipfix/assets/images/<?php echo $current_profile_picture; ?>" alt="Profile Picture" class="profile-picture mb-3">
                            
                            <div class="mb-3">
                                <label for="profile_picture" class="form-label">Change Profile Picture</label>
                                <input type="file" class="form-control <?php echo isset($errors['profile_picture']) ? 'is-invalid' : ''; ?>" id="profile_picture" name="profile_picture" accept="image/*">
                                <?php if (isset($errors['profile_picture'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['profile_picture']; ?></div>
                                <?php endif; ?>
                                <div class="form-text">Max size: 5MB. Formats: JPG, JPEG, PNG, GIF</div>
                            </div>
                        </div>
                        
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($username); ?>" disabled>
                                <div class="form-text">Username cannot be changed</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>">
                                <?php if (isset($errors['email'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-3">
                                <label for="bio" class="form-label">Bio</label>
                                <textarea class="form-control <?php echo isset($errors['bio']) ? 'is-invalid' : ''; ?>" id="bio" name="bio" rows="3" maxlength="500"><?php echo htmlspecialchars($bio); ?></textarea>
                                <?php if (isset($errors['bio'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['bio']; ?></div>
                                <?php endif; ?>
                                <div class="form-text">Maximum 500 characters</div>
                            </div>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <h5 class="mb-3">Change Password</h5>
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password" class="form-control <?php echo isset($errors['current_password']) ? 'is-invalid' : ''; ?>" id="current_password" name="current_password">
                        <?php if (isset($errors['current_password'])): ?>
                            <div class="invalid-feedback"><?php echo $errors['current_password']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" class="form-control <?php echo isset($errors['new_password']) ? 'is-invalid' : ''; ?>" id="new_password" name="new_password">
                        <?php if (isset($errors['new_password'])): ?>
                            <div class="invalid-feedback"><?php echo $errors['new_password']; ?></div>
                        <?php endif; ?>
                        <div class="form-text">Minimum 6 characters</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" id="confirm_password" name="confirm_password">
                        <?php if (isset($errors['confirm_password'])): ?>
                            <div class="invalid-feedback"><?php echo $errors['confirm_password']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="text-end">
                        <a href="../index.php" class="btn btn-secondary me-2">Cancel</a>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>