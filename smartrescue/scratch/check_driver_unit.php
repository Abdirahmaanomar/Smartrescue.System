<?php
require_once __DIR__ . '/../config/db.php';

// Find the driver user
$driver_q = "SELECT id, fullname, role FROM users WHERE role = 'driver' OR fullname LIKE '%Maxamed%'";
$driver_res = mysqli_query($conn, $driver_q);

echo "--- Drivers Found ---\n";
while ($row = mysqli_fetch_assoc($driver_res)) {
    echo "ID: {$row['id']} | Name: {$row['fullname']} | Role: {$row['role']}\n";
}

echo "\n--- Emergency Units ---\n";
$unit_q = "SELECT id, unit_name, plate_number, driver_id, status FROM emergency_units";
$unit_res = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM emergency_units"));
echo "Total emergency units: {$unit_res['cnt']}\n";

$unit_res = mysqli_query($conn, $unit_q);
while ($row = mysqli_fetch_assoc($unit_res)) {
    echo "ID: {$row['id']} | Name: {$row['unit_name']} | Plate: {$row['plate_number']} | Driver ID: " . ($row['driver_id'] ?? 'NULL') . " | Status: {$row['status']}\n";
}
?>
