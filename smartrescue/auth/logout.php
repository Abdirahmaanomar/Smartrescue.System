<?php
session_start();
require_once '../config/db.php';

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $role = $_SESSION['role'] ?? '';

    if ($role === 'driver') {
        // Set unit status to offline
        mysqli_query($conn, "UPDATE emergency_units SET status = 'offline' WHERE driver_id = '$user_id'");
    } elseif ($role === 'user') {
        // Clear live location tracking data
        mysqli_query($conn, "UPDATE users SET current_lat = NULL, current_lng = NULL WHERE id = '$user_id'");
    }
}

session_unset();
session_destroy();
header("Location: login.php");
exit();
?>