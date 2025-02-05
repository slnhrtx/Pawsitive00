<?php
function enhanceSessionSecurity() {
    // Regenerate session ID after 30 minutes to prevent session fixation
    if (!isset($_SESSION['CREATED'])) {
        $_SESSION['CREATED'] = time();
    } elseif (time() - $_SESSION['CREATED'] > 1800) { // 30 min expiry
        session_regenerate_id(true);
        $_SESSION['CREATED'] = time();
    }

    // Auto-logout after 15 minutes of inactivity
    if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 900)) {
        session_unset();      // Clear session data
        session_destroy();    // Destroy session
        header("Location: ../public/staff_login.php"); // Redirect to login page
        exit();
    }

    $_SESSION['LAST_ACTIVITY'] = time(); // Update last activity timestamp
}
?>