<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
}

// Query: get all drivers joined with their emergency units
$sql = "
    SELECT 
        u.id,
        u.fullname,
        u.phone,
        u.profile_image,
        u.gender,
        eu.id AS unit_id,
        eu.unit_name,
        eu.unit_type,
        eu.plate_number,
        eu.status AS unit_status,
        eu.current_lat,
        eu.current_lng
    FROM users u
    LEFT JOIN emergency_units eu ON eu.driver_id = u.id
    WHERE u.role = 'driver'
    ORDER BY eu.status ASC, u.fullname ASC
";

$result = mysqli_query($conn, $sql);

if (!$result) {
    echo json_encode(['status' => 'error', 'message' => 'Query failed: ' . mysqli_error($conn)]);
    exit();
}

$responders = [];
while ($row = mysqli_fetch_assoc($result)) {
    $responders[] = [
        'id'           => (int)$row['id'],
        'fullname'     => $row['fullname'] ?? '',
        'phone'        => $row['phone'] ?? '',
        'profile_image'=> $row['profile_image'] ?? '',
        'gender'       => $row['gender'] ?? '',
        'unit_id'      => $row['unit_id'] ? (int)$row['unit_id'] : null,
        'unit_name'    => $row['unit_name'] ?? '',
        'unit_type'    => $row['unit_type'] ?? '',
        'plate_number' => $row['plate_number'] ?? '',
        'unit_status'  => $row['unit_status'] ?? 'unavailable',
        'current_lat'  => $row['current_lat'] ? (float)$row['current_lat'] : null,
        'current_lng'  => $row['current_lng'] ? (float)$row['current_lng'] : null,
    ];
}

echo json_encode(['status' => 'success', 'data' => $responders, 'total' => count($responders)]);
?>
