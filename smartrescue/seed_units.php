<?php
/**
 * One-time seed script: Create emergency units and assign drivers.
 * Run once, then delete or ignore.
 */
header("Content-Type: application/json");
require_once 'config/db.php';

$results = [];

// Check if units already exist
$existing = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM emergency_units");
$row = mysqli_fetch_assoc($existing);
if ($row['cnt'] > 0) {
    echo json_encode(["status" => "skipped", "message" => "Units already exist ({$row['cnt']} found)"]);
    exit();
}

// Find driver users, or create some
$driver_check = mysqli_query($conn, "SELECT id FROM users WHERE role = 'driver' LIMIT 4");
$driver_ids = [];
while ($d = mysqli_fetch_assoc($driver_check)) {
    $driver_ids[] = (int)$d['id'];
}

// If no drivers exist, create test drivers
if (count($driver_ids) < 4) {
    $test_drivers = [
        ['fullname' => 'Cabdi Maxamed', 'phone' => '615001001', 'email' => NULL],
        ['fullname' => 'Faadumo Cali',  'phone' => '615002002', 'email' => NULL],
        ['fullname' => 'Xasan Nuur',    'phone' => '615003003', 'email' => NULL],
        ['fullname' => 'Hodan Warsame', 'phone' => '615004004', 'email' => NULL],
    ];
    
    $hashed = password_hash('driver123', PASSWORD_BCRYPT);
    
    foreach ($test_drivers as $td) {
        // Check if phone already exists
        $chk = mysqli_query($conn, "SELECT id FROM users WHERE phone = '{$td['phone']}'");
        if (mysqli_num_rows($chk) == 0) {
            mysqli_query($conn, "INSERT INTO users (fullname, phone, password, role) VALUES ('{$td['fullname']}', '{$td['phone']}', '$hashed', 'driver')");
            $driver_ids[] = mysqli_insert_id($conn);
        } else {
            $existing_driver = mysqli_fetch_assoc($chk);
            $driver_ids[] = (int)$existing_driver['id'];
        }
    }
    $results[] = "Created/found " . count($driver_ids) . " driver accounts";
}

// Seed emergency units
$units = [
    ['unit_name' => 'Ambulance Alpha-1',  'unit_type' => 'Ambulance',    'plate_number' => 'AMB-2024-01', 'driver_idx' => 0, 'lat' => 2.0469, 'lng' => 45.3182],
    ['unit_name' => 'Fire Engine Bravo',   'unit_type' => 'Fire Truck',   'plate_number' => 'FRE-2024-02', 'driver_idx' => 1, 'lat' => 2.0455, 'lng' => 45.3200],
    ['unit_name' => 'Patrol Unit Charlie', 'unit_type' => 'Police',       'plate_number' => 'POL-2024-03', 'driver_idx' => 2, 'lat' => 2.0480, 'lng' => 45.3165],
    ['unit_name' => 'Rescue Unit Delta',   'unit_type' => 'Rescue',       'plate_number' => 'RES-2024-04', 'driver_idx' => 3, 'lat' => 2.0440, 'lng' => 45.3190],
];

foreach ($units as $u) {
    $driver_id = isset($driver_ids[$u['driver_idx']]) ? $driver_ids[$u['driver_idx']] : 'NULL';
    $sql = "INSERT INTO emergency_units (unit_name, unit_type, plate_number, status, driver_id, current_lat, current_lng) 
            VALUES ('{$u['unit_name']}', '{$u['unit_type']}', '{$u['plate_number']}', 'available', '$driver_id', '{$u['lat']}', '{$u['lng']}')";
    if (mysqli_query($conn, $sql)) {
        $results[] = "Created unit: {$u['unit_name']}";
    } else {
        $results[] = "Failed: {$u['unit_name']} - " . mysqli_error($conn);
    }
}

echo json_encode(["status" => "success", "results" => $results], JSON_PRETTY_PRINT);
?>
