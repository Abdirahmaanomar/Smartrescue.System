<?php
header("Content-Type: application/json");
session_start();
require_once '../../config/db.php';

$user_id = null;
if (isset($_REQUEST['user_id'])) {
    $user_id = intval($_REQUEST['user_id']);
} elseif (isset($_SESSION['user_id'])) {
    $user_id = intval($_SESSION['user_id']);
}

if (!$user_id) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

$sql = "SELECT * FROM (
            SELECT 
                rr.id,
                rr.lat,
                rr.lng,
                rr.emergency_type,
                rr.status,
                rr.description,
                rr.created_at,
                rr.updated_at,
                rr.assigned_unit_id,
                eu.unit_name,
                eu.unit_type,
                eu.plate_number,
                eu.current_lat AS driver_lat,
                eu.current_lng AS driver_lng,
                u.fullname AS driver_name,
                u.phone AS driver_phone
            FROM rescue_requests rr
            LEFT JOIN emergency_units eu ON eu.id = rr.assigned_unit_id
            LEFT JOIN users u ON u.id = eu.driver_id
            WHERE rr.user_id = '$user_id'
            ORDER BY rr.created_at DESC
            LIMIT 1
        ) as latest
        WHERE latest.status NOT IN ('completed', 'cancelled')
           OR ((latest.status = 'completed' OR latest.status = 'cancelled') AND latest.updated_at >= NOW() - INTERVAL 2 MINUTE)";

$result = mysqli_query($conn, $sql);
$row = $result ? mysqli_fetch_assoc($result) : null;

if ($row) {
    $driver_lat = $row['driver_lat'] !== null ? (float)$row['driver_lat'] : null;
    $driver_lng = $row['driver_lng'] !== null ? (float)$row['driver_lng'] : null;
    $lat = (float)$row['lat'];
    $lng = (float)$row['lng'];

    if ($driver_lat !== null && $driver_lng !== null) {
        // Returned exactly as stored in database for 100% live accuracy
    }

    echo json_encode([
        "status"          => "success",
        "id"              => (int)$row['id'],
        "lat"             => $lat,
        "lng"             => $lng,
        "request_status"  => $row['status'],
        "emergency_type"  => $row['emergency_type'] ?? 'Unknown',
        "description"     => $row['description'] ?? '',
        "created_at"      => $row['created_at'] ?? '',
        "driver_assigned" => !empty($row['assigned_unit_id']),
        "driver_name"     => $row['driver_name'] ?? '',
        "driver_phone"    => $row['driver_phone'] ?? '',
        "unit_name"       => $row['unit_name'] ?? '',
        "plate_number"    => $row['plate_number'] ?? '',
        "unit_type"       => $row['unit_type'] ?? '',
        "driver_lat"      => $driver_lat,
        "driver_lng"      => $driver_lng,
    ]);
} else {
    echo json_encode(["status" => "no_active_request"]);
}
?>

