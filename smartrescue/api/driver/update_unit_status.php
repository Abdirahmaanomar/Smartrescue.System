<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$driver_id = $_SESSION['user_id'];
$status = mysqli_real_escape_string($conn, $_POST['status']);

// Get driver's unit
$unit_query = "SELECT id FROM emergency_units WHERE driver_id = '$driver_id' LIMIT 1";
$unit_res = mysqli_query($conn, $unit_query);
$unit = mysqli_fetch_assoc($unit_res);

if ($unit) {
    if (mysqli_query($conn, "UPDATE emergency_units SET status = '$status' WHERE id = '{$unit['id']}'")) {
        echo json_encode(['status' => 'success', 'message' => 'Unit status updated to ' . $status]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update status']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'No unit assigned to driver']);
}
?>
