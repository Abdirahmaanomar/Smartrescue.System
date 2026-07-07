<?php
/**
 * SmartRescue Session Guard
 * Enforces single-session logins. If a user logs in from another device,
 * the older session is automatically terminated.
 *
 * PERFORMANCE: The DB check is rate-limited to once every 60 seconds per session
 * using a session-cached timestamp. This prevents a Railway DB round-trip on
 * every single page load and API call while still catching session takeovers quickly.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only check if user is logged in
if (isset($_SESSION['user_id'])) {
    $check_user_id = $_SESSION['user_id'];
    $current_sid   = session_id();

    // ── Rate-limit the DB check to once per 60 seconds ──
    // On a Railway cloud DB, this saves a round-trip query on every single request.
    $cache_key    = '_sg_checked_' . $check_user_id;
    $cache_expiry = 60; // seconds between actual DB checks
    $now          = time();

    $needs_check = !isset($_SESSION[$cache_key]) || ($now - $_SESSION[$cache_key]) >= $cache_expiry;

    if ($needs_check) {
        require_once __DIR__ . '/../config/db.php';

        // Fetch the authorized session ID from the database
        $stmt = mysqli_prepare($conn, "SELECT last_session_id FROM users WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $check_user_id);
        mysqli_stmt_execute($stmt);
        $result       = mysqli_stmt_get_result($stmt);
        $user_session = mysqli_fetch_assoc($result);

        if ($user_session) {
            $last_sid         = $user_session['last_session_id'];
            $is_session_valid = ($last_sid === null || $last_sid === $current_sid);

            if (!$is_session_valid) {
                // Log the mismatch
                error_log("Session mismatch for user $check_user_id. Expected $last_sid, got $current_sid. Terminating session.");

                // Clear & destroy session
                $_SESSION = array();
                if (ini_get("session.use_cookies")) {
                    $params = session_get_cookie_params();
                    setcookie(session_name(), '', time() - 42000,
                        $params["path"], $params["domain"],
                        $params["secure"], $params["httponly"]
                    );
                }
                session_destroy();

                // If silent mode is requested (for APIs), don't redirect
                if (defined('SESSION_GUARD_SILENT')) {
                    return;
                }

                // Redirect to login
                $current_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
                $path_to_root = './';
                if (strpos($current_dir, '/admin') !== false || strpos($current_dir, '/user') !== false || strpos($current_dir, '/driver') !== false) {
                    $path_to_root = '../';
                }
                header("Location: " . $path_to_root . "auth/login.php?error=session_expired");
                exit();
            }

            // Stamp the successful check time — skip DB for the next 60s
            $_SESSION[$cache_key] = $now;
        }
    }
}
?>
