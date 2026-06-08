<?php
header("Content-Type: application/json");
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

$driver_id = $_SESSION['user_id'];

// Find the unit assigned to this driver
$unit_q = "SELECT id FROM emergency_units WHERE driver_id = '$driver_id' LIMIT 1";
$unit_r = mysqli_query($conn, $unit_q);
$unit   = mysqli_fetch_assoc($unit_r);

if (!$unit) {
    echo json_encode(["status" => "no_unit"]);
    exit();
}

$unit_id = $unit['id'];

// Get latest active rescue request assigned to this unit
$sql = "SELECT r.id, r.lat, r.lng, r.emergency_type, r.status,
               u.fullname AS patient_name, u.phone AS patient_phone
        FROM rescue_requests r
        JOIN users u ON r.user_id = u.id
        WHERE r.assigned_unit_id = '$unit_id'
          AND r.status = 'accepted'
        ORDER BY r.created_at DESC
        LIMIT 1";

$result = mysqli_query($conn, $sql);

if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    echo json_encode([
        "status"        => "success",
        "victim_lat"    => $row['lat'] !== null ? (float)$row['lat'] : null,
        "victim_lng"    => $row['lng'] !== null ? (float)$row['lng'] : null,
        "patient_name"  => $row['patient_name'],
        "patient_phone" => $row['patient_phone'],
        "emergency"     => $row['emergency_type'],
        "request_id"    => $row['id']
    ]);
} else {
    echo json_encode(["status" => "no_active_job"]);
}
?>
