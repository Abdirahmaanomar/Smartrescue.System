<?php
header("Content-Type: application/json");
session_start();
require_once '../../config/db.php';

// Support both POST/GET and fallback to session
$notification_id = isset($_REQUEST['notification_id']) ? intval($_REQUEST['notification_id']) : null;
$user_id = isset($_REQUEST['user_id']) ? intval($_REQUEST['user_id']) : (isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null);

if (!$notification_id || !$user_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing notification_id or user_id'
    ]);
    exit();
}

// Security check: only delete the notification if it belongs to this user
$sql = "DELETE FROM notifications WHERE id = '$notification_id' AND user_id = '$user_id'";
$result = mysqli_query($conn, $sql);

if ($result && mysqli_affected_rows($conn) > 0) {
    echo json_encode([
        'success' => true,
        'message' => 'Ogeysiiska waa la tirtiray (Notification deleted successfully)'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Lama helin ogeysiiska ama ma lihid xuquuq (Notification not found or access denied)'
    ]);
}
?>
