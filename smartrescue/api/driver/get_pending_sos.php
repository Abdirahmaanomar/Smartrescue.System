<?php
header("Content-Type: application/json");
session_start();
require_once '../../config/db.php';

$driver_id = null;
if (isset($_REQUEST['driver_id'])) {
    $driver_id = intval($_REQUEST['driver_id']);
} elseif (isset($_SESSION['user_id']) && $_SESSION['role'] === 'driver') {
    $driver_id = intval($_SESSION['user_id']);
}

if (!$driver_id) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}
$unit_res  = mysqli_query($conn, "SELECT id FROM emergency_units WHERE driver_id='$driver_id' LIMIT 1");
$unit      = mysqli_fetch_assoc($unit_res);
$unit_id   = $unit ? $unit['id'] : 0;

if (!$unit_id) {
    echo json_encode(["status" => "success", "count" => 0, "requests" => []]);
    exit();
}

// Fetch requests assigned to THIS unit that driver hasn't responded to yet
$sql = "SELECT r.id, r.lat, r.lng, r.emergency_type, r.description,
               r.created_at, r.status,
               u.fullname, u.phone
        FROM rescue_requests r
        JOIN users u ON r.user_id = u.id
        WHERE r.status IN ('pending', 'accepted')
          AND r.assigned_unit_id = '$unit_id'
        ORDER BY r.created_at DESC
        LIMIT 5";

$result = mysqli_query($conn, $sql);

$requests = [];
while ($row = mysqli_fetch_assoc($result)) {
    $row['time_formatted'] = date('h:i A', strtotime($row['created_at']));
    $row['time_ago']       = human_time_diff($row['created_at']);
    $requests[] = $row;
}

echo json_encode([
    "status"   => "success",
    "count"    => count($requests),
    "requests" => $requests
]);

function human_time_diff($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return $diff . "s ago";
    if ($diff < 3600) return floor($diff / 60) . "m ago";
    return floor($diff / 3600) . "h ago";
}
?>
