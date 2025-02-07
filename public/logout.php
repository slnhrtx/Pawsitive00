<?php
session_start();

// Ensure session exists before destroying it
if (session_status() === PHP_SESSION_ACTIVE) {
    
    // Regenerate session ID before destroying to prevent session fixation attacks
    session_regenerate_id(true);
    
    // Unset all session variables
    $_SESSION = [];

    // Destroy session cookie securely
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), '', time() - 42000, 
            $params["path"], $params["domain"], 
            $params["secure"], $params["httponly"]
        );
    }

    // Destroy the session completely
    session_destroy();
}

// Prevent caching of the logout page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT"); // Force expiration
header("Pragma: no-cache");

// Redirect to login page with a flash message
session_start();
$_SESSION['logout_message'] = 'You have been logged out successfully.';

header("Location: staff_login.php");
exit;
?>