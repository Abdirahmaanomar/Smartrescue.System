<?php
header("Content-Type: application/json");
session_start();
require_once '../../config/db.php';

// Self-healing: create blood_donors table if not exists
$tables = mysqli_query($conn, "SHOW TABLES LIKE 'blood_donors'");
if (mysqli_num_rows($tables) == 0) {
    mysqli_query($conn, "CREATE TABLE blood_donors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        blood_type VARCHAR(10) NOT NULL,
        phone VARCHAR(50) NOT NULL,
        lat DECIMAL(10,8) NULL,
        lng DECIMAL(11,8) NULL,
        is_available TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $defaults = [
        ['Kumar maxamed', 'B+', '689109810'],
        ['Dr. Abdirahman Jama', 'O+', '615112233'],
        ['Amino Hassan', 'A+', '615223344'],
        ['Farhiya Ali', 'B+', '615334455'],
        ['Mohamud Omar', 'AB+', '615445566']
    ];
    foreach ($defaults as $d) {
        $n = mysqli_real_escape_string($conn, $d[0]);
        $b = mysqli_real_escape_string($conn, $d[1]);
        $p = mysqli_real_escape_string($conn, $d[2]);
        mysqli_query($conn, "INSERT INTO blood_donors (name, blood_type, phone, is_available) VALUES ('$n', '$b', '$p', 1)");
    }
}

// Fetch available blood donors
$result = mysqli_query($conn, "
    SELECT id, name, blood_type, phone, lat, lng, is_available
    FROM blood_donors
    WHERE is_available = 1
    ORDER BY id DESC
    LIMIT 50
");

$donors = [];
if ($result && mysqli_num_rows($result) > 0) {
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
} else {
    // Fallback default donor list
    $donors = [
        ['id' => 1, 'name' => 'Kumar maxamed', 'blood_type' => 'B+', 'phone' => '689109810', 'is_available' => true],
        ['id' => 2, 'name' => 'Dr. Abdirahman Jama', 'blood_type' => 'O+', 'phone' => '615112233', 'is_available' => true],
        ['id' => 3, 'name' => 'Amino Hassan', 'blood_type' => 'A+', 'phone' => '615223344', 'is_available' => true]
    ];
}

echo json_encode(['success' => true, 'donors' => $donors]);
?>
