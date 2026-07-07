<?php
header("Content-Type: application/json");
session_start();
require_once '../../config/db.php';

// Fetch available blood donors

$result = mysqli_query($conn, "
    SELECT id, name, blood_type, phone, lat, lng, is_available
    FROM blood_donors
    WHERE is_available = 1
    ORDER BY created_at DESC
    LIMIT 50
");

$donors = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $donors[] = [
            'id'           => (int) $row['id'],
            'name'         => $row['name'],
            'blood_type'   => $row['blood_type'],
            'phone'        => $row['phone'],
            'lat'          => $row['lat'] ? (float) $row['lat'] : null,
            'lng'          => $row['lng'] ? (float) $row['lng'] : null,
            'is_available' => (bool) $row['is_available'],
        ];
    }
}

echo json_encode(['success' => true, 'donors' => $donors]);
?>
