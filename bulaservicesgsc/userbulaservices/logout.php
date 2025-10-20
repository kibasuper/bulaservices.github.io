<?php
// Start session
session_name('BARANGAY_BULA_SESSID');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Destroy all session data
$_SESSION = [];

// Delete session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Redirect to login page
header("Location: index.php?message=You have been logged out successfully");
exit();