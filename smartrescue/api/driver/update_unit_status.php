<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
session_start();
require_once '../../config/db.php';

$driver_id = null;
if (isset($_REQUEST['driver_id'])) {
    $driver_id = intval($_REQUEST['driver_id']);
} elseif (isset($_SESSION['user_id']) && $_SESSION['role'] === 'driver') {
    $driver_id = intval($_SESSION['user_id']);
}

if (!$driver_id) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}
$status = isset($_POST['status']) ? mysqli_real_escape_string($conn, $_POST['status']) : null;
$log_file = __DIR__ . '/../../scratch/api_log.txt';
$log_data = "--- [update_unit_status] " . date('Y-m-d H:i:s') . " ---\n";
$log_data .= "POST: " . json_encode($_POST) . "\n";
$log_data .= "REQUEST: " . json_encode($_REQUEST) . "\n";
$log_data .= "Driver ID: " . $driver_id . "\n";

if (!$status || !in_array($status, ['available', 'offline', 'busy'])) {
    $resp = json_encode(['status' => 'error', 'message' => 'Invalid or missing status']);
    $log_data .= "Response: " . $resp . "\n\n";
    file_put_contents($log_file, $log_data, FILE_APPEND);
    echo $resp;
    exit();
}

// Get driver's unit
$unit_query = "SELECT id FROM emergency_units WHERE driver_id = '$driver_id' LIMIT 1";
$unit_res = mysqli_query($conn, $unit_query);
$unit = mysqli_fetch_assoc($unit_res);

if (!$unit) {
    // Proactively auto-assign or create a default unit for this driver if they exist in the users table
    $drv_check = mysqli_query($conn, "SELECT fullname FROM users WHERE id = '$driver_id' AND role = 'driver' LIMIT 1");
    if ($drv_check && mysqli_num_rows($drv_check) > 0) {
        $drv_info = mysqli_fetch_assoc($drv_check);
        $drv_name = mysqli_real_escape_string($conn, $drv_info['fullname']);
        $unit_name = $drv_name . " Unit";
        
        $types = ['medical', 'fire', 'police', 'accident'];
        $type = $types[rand(0, 3)];
        $plate = "SOM-" . rand(100, 999) . "-DRV";
        
        $create_unit = "INSERT INTO emergency_units (unit_name, unit_type, plate_number, status, driver_id, current_lat, current_lng)
                        VALUES ('$unit_name', '$type', '$plate', 'available', '$driver_id', 2.0469, 45.3182)";
        if (mysqli_query($conn, $create_unit)) {
            // Re-fetch unit
            $unit_res = mysqli_query($conn, $unit_query);
            $unit   = mysqli_fetch_assoc($unit_res);
        }
    }
}

if ($unit) {
    if (mysqli_query($conn, "UPDATE emergency_units SET status = '$status' WHERE id = '{$unit['id']}'")) {
        $resp = json_encode(['status' => 'success', 'message' => 'Unit status updated to ' . $status]);
    } else {
        $resp = json_encode(['status' => 'error', 'message' => 'Failed to update status']);
    }
} else {
    $resp = json_encode(['status' => 'error', 'message' => 'No unit assigned to driver']);
}
$log_data .= "Response: " . $resp . "\n\n";
file_put_contents($log_file, $log_data, FILE_APPEND);
echo $resp;
exit();
?>
