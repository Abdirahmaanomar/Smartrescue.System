<?php
session_start();
header("Content-Type: application/json");
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $request_id = clean_input($_POST['request_id'], $conn);
    $unit_id = clean_input($_POST['unit_id'], $conn);

    if (!$request_id || !$unit_id) {
        echo json_encode(['status' => 'error', 'message' => 'Missing data.']);
        exit();
    }

    $check_unit = mysqli_query($conn, "SELECT id, status FROM emergency_units WHERE id = '$unit_id'");
    $unit_data = mysqli_fetch_assoc($check_unit);

    if (!$unit_data) {
        echo json_encode(['status' => 'error', 'message' => 'Selected unit not found.']);
        exit();
    }

    $q1 = "UPDATE rescue_requests SET assigned_unit_id = '$unit_id', status = 'accepted' WHERE id = '$request_id'";
    $q2 = "UPDATE emergency_units SET status = 'busy' WHERE id = '$unit_id'";

    if (mysqli_query($conn, $q1) && mysqli_query($conn, $q2)) {
        // Send notification to the victim user
        $req_info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT user_id, emergency_type FROM rescue_requests WHERE id = '$request_id' LIMIT 1"));
        if ($req_info && !empty($req_info['user_id'])) {
            $uid = $req_info['user_id'];
            $etype = ucfirst($req_info['emergency_type'] ?? 'Emergency');
            $notif_title = mysqli_real_escape_string($conn, "🚑 Unit Dispatched");
            $notif_msg   = mysqli_real_escape_string($conn, "A $etype responder has been assigned and dispatched to your location. Stay calm and keep your phone ready.");
            mysqli_query($conn, "INSERT INTO notifications (user_id, title, message, is_read) VALUES ('$uid', '$notif_title', '$notif_msg', 0)");
        }
        echo json_encode(['status' => 'success', 'message' => 'Unit successfully dispatched.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . mysqli_error($conn)]);
    }
}
?>
