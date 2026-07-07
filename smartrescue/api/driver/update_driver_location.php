<?php
header("Content-Type: application/json");
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $driver_id = intval($_SESSION['user_id']);

    // Cast to float and validate range — reject obviously wrong values
    $lat = isset($_POST['lat']) ? floatval($_POST['lat']) : null;
    $lng = isset($_POST['lng']) ? floatval($_POST['lng']) : null;

    if ($lat === null || $lng === null || $lat == 0.0 || $lng == 0.0 ||
        $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        echo json_encode(["status" => "error", "message" => "Invalid coordinates"]);
        exit();
    }

    // Use prepared statement to prevent SQL injection
    $stmt = $conn->prepare("UPDATE emergency_units SET current_lat = ?, current_lng = ? WHERE driver_id = ?");
    $stmt->bind_param("ddi", $lat, $lng, $driver_id);

    if ($stmt->execute()) {
        $stmt->close();
        
        // Also update the users table for consistency across the platform
        $stmt2 = $conn->prepare("UPDATE users SET current_lat = ?, current_lng = ? WHERE id = ?");
        $stmt2->bind_param("ddi", $lat, $lng, $driver_id);
        $stmt2->execute();
        $stmt2->close();
        
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => $stmt->error]);
        $stmt->close();
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid Request"]);
}
?>
