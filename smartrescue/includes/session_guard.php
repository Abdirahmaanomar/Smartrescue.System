<?php
/**
 * SmartRescue Session Guard
 * Enforces single-session logins. If a user logs in from another device, 
 * the older session is automatically terminated.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only check if user is logged in
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../config/db.php';
    
    $check_user_id = $_SESSION['user_id'];
    $current_sid = session_id();
    
    // Fetch the authorized session ID from the database
    $stmt = mysqli_prepare($conn, "SELECT last_session_id FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $check_user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user_session = mysqli_fetch_assoc($result);
    
    if ($user_session) {
        $last_sid = $user_session['last_session_id'];
        $is_session_valid = ($last_sid === null || $last_sid === $current_sid);
        
        // If DB session ID exists and doesn't match current one, terminate!
        if (!$is_session_valid) {
            // Log the mismatch (optional)
            error_log("Session mismatch for user $check_user_id. Expected $last_sid, got $current_sid. Terminating session.");
            
            // Clear session data
            $_SESSION = array();
            
            // Destroy session cookie
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            
            // Destroy session
            session_destroy();
            
            // If silent mode is requested (for APIs), don't redirect
            if (defined('SESSION_GUARD_SILENT')) {
                return; // Let the API handle it
            }
            
            // Redirect to login with alert
            // Find the correct path to auth/login.php based on current location
            $current_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
            $path_to_root = './';
            if (strpos($current_dir, '/admin') !== false || strpos($current_dir, '/user') !== false || strpos($current_dir, '/driver') !== false) {
                $path_to_root = '../';
            }
            
            header("Location: " . $path_to_root . "auth/login.php?error=session_expired");
            exit();
        }
    }
}
