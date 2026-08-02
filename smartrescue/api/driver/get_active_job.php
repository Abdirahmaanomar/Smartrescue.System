<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
session_start();

// Log diagnostics
$log_file = __DIR__ . '/../../scratch/api_log.txt';
$headers = function_exists('getallheaders') ? getallheaders() : [];
$log_data = "--- " . date('Y-m-d H:i:s') . " ---\n";
$log_data .= "Session: " . json_encode($_SESSION) . "\n";
$log_data .= "Session ID: " . session_id() . "\n";
$log_data .= "Headers: " . json_encode($headers) . "\n";

function log_and_respond($data, $log_file, $log_data) {
    $resp = json_encode($data);
    $log_data .= "Response: " . $resp . "\n\n";
    file_put_contents($log_file, $log_data, FILE_APPEND);
    echo $resp;
    exit();
}

require_once '../../config/db.php';

$driver_id = null;
if (isset($_REQUEST['driver_id'])) {
    $driver_id = intval($_REQUEST['driver_id']);
} elseif (isset($_SESSION['user_id']) && $_SESSION['role'] === 'driver') {
    $driver_id = intval($_SESSION['user_id']);
}

if (!$driver_id) {
    log_and_respond(["status" => "error", "message" => "Unauthorized"], $log_file, $log_data);
}

// Find the unit assigned to this driver
$unit_q = "SELECT id, unit_name, unit_type, plate_number, status 
           FROM emergency_units 
           WHERE driver_id = '$driver_id' LIMIT 1";
$unit_r = mysqli_query($conn, $unit_q);
$unit   = mysqli_fetch_assoc($unit_r);

if (!$unit) {
    // Proactively auto-assign or create a default unit for this driver if they exist in the users table
    $drv_check = mysqli_query($conn, "SELECT fullname FROM users WHERE id = '$driver_id' AND role = 'driver' LIMIT 1");
    if ($drv_check && mysqli_num_rows($drv_check) > 0) {
        $drv_info = mysqli_fetch_assoc($drv_check);
        $drv_name = mysqli_real_escape_string($conn, $drv_info['fullname']);
        $unit_name = $drv_name . " Unit";
        
        // Pick a default type based on rand
        $types = ['medical', 'fire', 'police', 'accident'];
        $type = $types[rand(0, 3)];
        $plate = "SOM-" . rand(100, 999) . "-DRV";
        
        $create_unit = "INSERT INTO emergency_units (unit_name, unit_type, plate_number, status, driver_id, current_lat, current_lng)
                        VALUES ('$unit_name', '$type', '$plate', 'offline', '$driver_id', 2.0469, 45.3182)";
        if (mysqli_query($conn, $create_unit)) {
            // Re-fetch unit
            $unit_r = mysqli_query($conn, $unit_q);
            $unit   = mysqli_fetch_assoc($unit_r);
        }
    }
}

if (!$unit) {
    log_and_respond(["status" => "no_unit", "unit" => null], $log_file, $log_data);
}

$unit_id = $unit['id'];

// Build unit info to always return
$unit_info = [
    "id"           => (int) $unit['id'],
    "unit_name"    => $unit['unit_name'],
    "unit_type"    => $unit['unit_type'],
    "plate_number" => $unit['plate_number'],
    "status"       => $unit['status'],  // 'available', 'busy', 'offline'
];

// Get latest active rescue request assigned to this unit including full status
$sql = "SELECT r.id, r.lat, r.lng, r.emergency_type, r.status, r.neighborhood,
               r.description,
               u.fullname AS patient_name, u.phone AS patient_phone
        FROM rescue_requests r
        JOIN users u ON r.user_id = u.id
        WHERE r.assigned_unit_id = '$unit_id'
          AND r.status IN ('pending', 'accepted', 'en_route', 'arrived')
        ORDER BY r.created_at DESC
        LIMIT 1";

$result = mysqli_query($conn, $sql);

if (!$result || mysqli_num_rows($result) === 0) {
    // Auto-assignment disabled — only admin can assign units manually via the dispatch panel.
    // Do not auto-assign here.
}

if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    log_and_respond([
        "status"        => "success",
        "unit"          => $unit_info,
        "request"       => [
            "id"            => (int) $row['id'],
            "lat"           => $row['lat'] !== null ? (float)$row['lat'] : null,
            "lng"           => $row['lng'] !== null ? (float)$row['lng'] : null,
            "status"        => $row['status'],
            "emergency_type"=> $row['emergency_type'] ?? 'Unknown',
            "description"   => $row['description'] ?? '',
            "neighborhood"  => $row['neighborhood'] ?? '',
            "patient_name"  => $row['patient_name'],
            "patient_phone" => $row['patient_phone'],
        ]
    ], $log_file, $log_data);
} else {
    log_and_respond(["status" => "no_active_job", "unit" => $unit_info], $log_file, $log_data);
}
?>
