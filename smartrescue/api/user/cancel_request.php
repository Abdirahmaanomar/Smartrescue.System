<?php
header("Content-Type: application/json");
session_start();
require_once '../../config/db.php';

// Support both raw user_id request input and session for resilience
$user_id = null;
if (isset($_REQUEST['user_id']) && intval($_REQUEST['user_id']) > 0) {
    $user_id = intval($_REQUEST['user_id']);
} elseif (isset($_SESSION['user_id'])) {
    $user_id = intval($_SESSION['user_id']);
}

if (!$user_id) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

$req_id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;

// 1. Fetch the request(s) to find the assigned units
if ($req_id > 0) {
    $stmt_unit = $conn->prepare("SELECT id, assigned_unit_id FROM rescue_requests WHERE id = ? AND user_id = ? AND status NOT IN ('completed', 'cancelled')");
    $stmt_unit->bind_param("ii", $req_id, $user_id);
} else {
    $stmt_unit = $conn->prepare("SELECT id, assigned_unit_id FROM rescue_requests WHERE user_id = ? AND status NOT IN ('completed', 'cancelled')");
    $stmt_unit->bind_param("i", $user_id);
}

$stmt_unit->execute();
$res = $stmt_unit->get_result();

$active_requests = [];
while ($row = $res->fetch_assoc()) {
    $active_requests[] = $row;
}
$stmt_unit->close();

if (empty($active_requests)) {
    echo json_encode(["status" => "success", "message" => "No active emergency to cancel"]);
    exit();
}

// 2. Update the status of request(s) to 'cancelled'
if ($req_id > 0) {
    $stmt = $conn->prepare("UPDATE rescue_requests SET status = 'cancelled' WHERE id = ? AND user_id = ? AND status NOT IN ('completed', 'cancelled')");
    $stmt->bind_param("ii", $req_id, $user_id);
} else {
    $stmt = $conn->prepare("UPDATE rescue_requests SET status = 'cancelled' WHERE user_id = ? AND status NOT IN ('completed', 'cancelled')");
    $stmt->bind_param("i", $user_id);
}

if ($stmt->execute()) {
    $stmt->close();
    
    // 3. Mark all assigned emergency units as available again
    foreach ($active_requests as $req) {
        $assigned_unit_id = $req['assigned_unit_id'];
        if ($assigned_unit_id) {
            $stmt_up = $conn->prepare("UPDATE emergency_units SET status = 'available' WHERE id = ?");
            $stmt_up->bind_param("i", $assigned_unit_id);
            $stmt_up->execute();
            $stmt_up->close();
        }
    }

    // 4. Create notification for the user
    mysqli_query($conn, "INSERT INTO notifications (user_id, title, message, is_read) VALUES ('$user_id', '🚨 Request Cancelled', 'Your emergency SOS request has been cancelled.', 0)");

    echo json_encode(["status" => "success", "message" => "Emergency cancelled successfully"]);
} else {
    echo json_encode(["status" => "error", "message" => $stmt->error]);
}
?>
