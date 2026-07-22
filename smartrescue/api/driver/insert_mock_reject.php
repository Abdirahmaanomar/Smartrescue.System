<?php
header('Content-Type: application/json');
require_once '../../config/db.php';

$driver_id = 1047; // Maxamed Qadar

// 1. Get unit for this driver
$unit_q = mysqli_query($conn, "SELECT id FROM emergency_units WHERE driver_id = '$driver_id' LIMIT 1");
$unit = mysqli_fetch_assoc($unit_q);

if (!$unit) {
    // If no unit, create one for testing
    mysqli_query($conn, "INSERT INTO emergency_units (unit_name, unit_type, plate_number, status, driver_id) VALUES ('Ambulance Test', 'Ambulance', 'TEST-001', 'available', '$driver_id')");
    $unit_id = mysqli_insert_id($conn);
} else {
    $unit_id = $unit['id'];
}

// 2. Create a mock rescue request
$insert_req = "INSERT INTO rescue_requests (user_id, lat, lng, accuracy, emergency_type, status, description, neighborhood) 
               VALUES (1, 2.0469, 45.3182, 10.0, 'Medical', 'pending', 'Test Mock Rejected Request', 'Hodan')";
mysqli_query($conn, $insert_req);
$request_id = mysqli_insert_id($conn);

// 3. Insert mock rejection in dispatches
$insert_disp = "INSERT INTO dispatches (request_id, unit_id, status) VALUES ('$request_id', '$unit_id', 'rejected')";
$ok = mysqli_query($conn, $insert_disp);

echo json_encode([
    'status' => $ok ? 'success' : 'error',
    'driver_id' => $driver_id,
    'unit_id' => $unit_id,
    'request_id' => $request_id,
    'db_error' => mysqli_error($conn)
]);
?>
