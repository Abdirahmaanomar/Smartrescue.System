<?php
header('Content-Type: application/json');

/**
 * verify_session.php
 * Endpoint for background heartbeat to check if the session is still valid.
 */

define('SESSION_GUARD_SILENT', true);

// This will trigger session_start and session_guard.php via the inclusion in db.php
require_once '../../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$response = [
    'valid' => false,
    'user_id' => null
];

if (isset($_SESSION['user_id'])) {
    // If we reach here and the session is still set, it means session_guard.php
    // didn't kill the session, or the session is indeed valid.
    // To be 100% sure, we can re-verify manually here if needed, 
    // but the guard in db.php already does it.
    
    $response['valid'] = true;
    $response['user_id'] = $_SESSION['user_id'];
}

echo json_encode($response);
