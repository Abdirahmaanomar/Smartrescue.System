<?php
header("Content-Type: application/json");
session_start();
require_once '../../config/db.php';

$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : (isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null);

if (!$user_id) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $lat = isset($_POST['lat']) ? floatval($_POST['lat']) : null;
    $lng = isset($_POST['lng']) ? floatval($_POST['lng']) : null;
    $acc = isset($_POST['accuracy']) ? floatval($_POST['accuracy']) : 0;

    if ($lat === null || $lng === null || $lat == 0.0 || $lng == 0.0 ||
        $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        echo json_encode(["status" => "error", "message" => "Invalid coordinates"]);
        exit();
    }

    // 1. Update the Users table (Always update last seen location)
    $stmt1 = $conn->prepare("UPDATE users SET current_lat = ?, current_lng = ? WHERE id = ?");
    $stmt1->bind_param("ddi", $lat, $lng, $user_id);
    $stmt1->execute();
    $stmt1->close();

    // 2. Update all active rescue requests' location for this user
    $stmt2 = $conn->prepare("UPDATE rescue_requests SET lat = ?, lng = ?, accuracy = ? WHERE user_id = ? AND status != 'completed' AND status != 'cancelled'");
    $stmt2->bind_param("dddi", $lat, $lng, $acc, $user_id);

    if ($stmt2->execute()) {
        echo json_encode(["status" => "success", "request_updated" => ($stmt2->affected_rows > 0)]);
    } else {
        echo json_encode(["status" => "error", "message" => "Database error"]);
    }
    $stmt2->close();
} else {
    echo json_encode(["status" => "error", "message" => "Invalid Request"]);
}
?>
