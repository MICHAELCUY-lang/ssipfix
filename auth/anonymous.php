<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

// Start session
ensure_session_started();

// Redirect if already logged in
if (is_logged_in()) {
    header("Location: ../index.php");
    exit;
}

// Get the anonymous user
$stmt = $pdo->query("SELECT * FROM users WHERE is_anonymous = TRUE LIMIT 1");
$anonymous_user = $stmt->fetch();

// If anonymous user doesn't exist, create it
if (!$anonymous_user) {
    $stmt = $pdo->query("INSERT INTO users (username, is_anonymous) VALUES ('Anonymous', TRUE)");
    
    // Get the newly created anonymous user
    $stmt = $pdo->query("SELECT * FROM users WHERE is_anonymous = TRUE LIMIT 1");
    $anonymous_user = $stmt->fetch();
}

// Set session variables
$_SESSION['user_id'] = $anonymous_user['user_id'];
$_SESSION['username'] = 'Anonymous';
$_SESSION['is_anonymous'] = true;

// Redirect to home page
header("Location: ../index.php");
exit;
?>