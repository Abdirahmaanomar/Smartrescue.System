<?php
session_start();
header("Content-Type: application/json");
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
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

    $check_unit = mysqli_query($conn, "SELECT status FROM emergency_units WHERE id = '$unit_id'");
    $unit_data = mysqli_fetch_assoc($check_unit);

    if (!$unit_data || $unit_data['status'] !== 'available') {
        echo json_encode(['status' => 'error', 'message' => 'Unit is no longer available.']);
        exit();
    }

    $q1 = "UPDATE rescue_requests SET assigned_unit_id = '$unit_id', status = 'accepted' WHERE id = '$request_id'";
    $q2 = "UPDATE emergency_units SET status = 'busy' WHERE id = '$unit_id'";

    if (mysqli_query($conn, $q1) && mysqli_query($conn, $q2)) {
        echo json_encode(['status' => 'success', 'message' => 'Unit successfully dispatched.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . mysqli_error($conn)]);
    }
}
?>
