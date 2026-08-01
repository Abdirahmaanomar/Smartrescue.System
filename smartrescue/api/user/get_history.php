<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
session_start();
require_once '../../config/db.php';

$user_id = null;
if (isset($_REQUEST['user_id'])) {
    $user_id = intval($_REQUEST['user_id']);
} elseif (isset($_SESSION['user_id'])) {
    $user_id = intval($_SESSION['user_id']);
}

if (!$user_id) {
    echo json_encode(['total_count' => 0, 'history' => []]);
    exit();
}

// Get the actual total count of requests for this user
$count_q = mysqli_query($conn, "SELECT COUNT(*) as total FROM rescue_requests WHERE user_id = '$user_id'");
$total_count = 0;
if ($count_q && $row = mysqli_fetch_assoc($count_q)) {
    $total_count = (int) $row['total'];
}

// Also expose via header for web clients
header("X-Total-Count: " . $total_count);
header("Access-Control-Expose-Headers: X-Total-Count");

$sql = "SELECT 
            rr.id,
            rr.lat,
            rr.lng,
            rr.emergency_type,
            rr.status,
            rr.description,
            rr.evidence_image,
            rr.neighborhood,
            rr.created_at,
            rr.assigned_unit_id,
            eu.unit_name,
            eu.unit_type,
            eu.plate_number,
            u.fullname AS driver_name,
            u.phone AS driver_phone
        FROM rescue_requests rr
        LEFT JOIN emergency_units eu ON eu.id = rr.assigned_unit_id
        LEFT JOIN users u ON u.id = eu.driver_id
        WHERE rr.user_id = '$user_id'
        ORDER BY rr.created_at DESC
        LIMIT 50";

$result = mysqli_query($conn, $sql);
$history = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $history[] = [
            'id' => (int) $row['id'],
            'lat' => (float) $row['lat'],
            'lng' => (float) $row['lng'],
            'emergency_type' => $row['emergency_type'] ?? 'Unknown',
            'status' => $row['status'] ?? 'pending',
            'description' => $row['description'] ?? '',
            'evidence_image' => $row['evidence_image'] ?? '',
            'neighborhood' => $row['neighborhood'] ?? '',
            'created_at' => $row['created_at'] ?? '',
            'driver_assigned' => !empty($row['assigned_unit_id']),
            'driver_name' => $row['driver_name'] ?? '',
            'driver_phone' => $row['driver_phone'] ?? '',
            'unit_name' => $row['unit_name'] ?? '',
            'plate_number' => $row['plate_number'] ?? '',
            'unit_type' => $row['unit_type'] ?? '',
        ];
    }
}

// Return as object with total_count embedded in body (reliable for all clients)
echo json_encode(['total_count' => $total_count, 'history' => $history]);
?>