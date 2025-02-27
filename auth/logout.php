<?php
require_once '../includes/functions.php';

// Start session
ensure_session_started();

// Clear session variables
$_SESSION = array();

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Delete the remember me cookie
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/', '', false, true);
}

if (isset($_COOKIE['user_id'])) {
    setcookie('user_id', '', time() - 3600, '/', '', false, true);
}

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: ../index.php");
exit;
?>