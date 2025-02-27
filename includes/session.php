<?php
require_once '../config/db.php';
require_once 'functions.php';

// Start session
ensure_session_started();

// Check if user is not logged in
if (!is_logged_in()) {
    // Check remember me cookie
    if (check_remember_cookie($pdo)) {
        // Redirect to home page if cookie is valid and session is set
        header("Location: ../index.php");
        exit;
    }
}
?>