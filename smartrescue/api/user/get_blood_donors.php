<?php
header("Content-Type: application/json");
session_start();
require_once '../../config/db.php';

// Hubi blood_donors table jirtaa, haddaan jirin samee
$check = mysqli_query($conn, "SHOW TABLES LIKE 'blood_donors'");
if (mysqli_num_rows($check) == 0) {
    mysqli_query($conn, "CREATE TABLE blood_donors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        blood_type VARCHAR(5) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        lat DECIMAL(10,8) NULL,
        lng DECIMAL(11,8) NULL,
        is_available TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
}

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
