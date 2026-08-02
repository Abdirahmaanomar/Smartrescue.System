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
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

// Find the unit assigned to this driver
$unit_q = "SELECT id, unit_name, unit_type, plate_number, status FROM emergency_units WHERE driver_id = '$driver_id' LIMIT 1";
$unit_r = mysqli_query($conn, $unit_q);
$unit   = mysqli_fetch_assoc($unit_r);

if (!$unit) {
    echo json_encode(["status" => "success", "history" => [], "saves" => 0, "unit" => null]);
    exit();
}

$unit_id = $unit['id'];

// Fetch all missions for this unit (assigned + rejected via dispatches)
$sql = "
    SELECT 
        r.id, r.user_id, r.lat, r.lng, r.accuracy, r.emergency_type, r.status, r.assigned_unit_id, 
        r.description, r.evidence_image, r.created_at, r.volunteer_id, r.neighborhood, r.updated_at,
        u.fullname as patient_name, u.phone as patient_phone 
    FROM rescue_requests r 
    JOIN users u ON r.user_id = u.id 
    WHERE r.assigned_unit_id = '$unit_id' 

    UNION ALL

    SELECT 
        r.id, r.user_id, r.lat, r.lng, r.accuracy, r.emergency_type, 'rejected' as status, NULL as assigned_unit_id, 
        r.description, r.evidence_image, d.assigned_at as created_at, r.volunteer_id, r.neighborhood, r.updated_at,
        u.fullname as patient_name, u.phone as patient_phone 
    FROM rescue_requests r 
    JOIN users u ON r.user_id = u.id 
    JOIN dispatches d ON d.request_id = r.id
    WHERE d.unit_id = '$unit_id' AND d.status = 'rejected'

    ORDER BY created_at DESC
";

$result = mysqli_query($conn, $sql);

$history = [];
$saves = 0;

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        if ($row['status'] === 'completed') {
            $saves++;
        }
        
        $history[] = [
            'id' => (int) $row['id'],
            'lat' => $row['lat'] !== null ? (float)$row['lat'] : null,
            'lng' => $row['lng'] !== null ? (float)$row['lng'] : null,
            'emergency_type' => $row['emergency_type'] ?? 'Unknown',
            'status' => $row['status'] ?? 'pending',
            'description' => $row['description'] ?? '',
            'created_at' => $row['created_at'] ?? '',
            'patient_name' => $row['patient_name'],
            'patient_phone' => $row['patient_phone'],
            'neighborhood' => $row['neighborhood'] ?? '',
        ];
    }
}

$saves_q   = mysqli_query($conn, "SELECT COUNT(*) c FROM rescue_requests WHERE assigned_unit_id='$unit_id' AND status='completed'");
$total_saves = (int)(mysqli_fetch_assoc($saves_q)['c'] ?? 0);

$missions_q  = mysqli_query($conn, "SELECT COUNT(*) c FROM rescue_requests WHERE assigned_unit_id='$unit_id'");
$total_missions = (int)(mysqli_fetch_assoc($missions_q)['c'] ?? 0);

$success_rate = $total_missions > 0 ? round(($total_saves / $total_missions) * 100) : 0;

echo json_encode([
    "status" => "success",
    "saves" => $total_saves,
    "total_missions" => $total_missions,
    "success_rate" => $success_rate,
    "unit" => [
        "id" => (int)$unit['id'],
        "unit_name" => $unit['unit_name'],
        "unit_type" => $unit['unit_type'],
        "plate_number" => $unit['plate_number'],
        "status" => $unit['status'],
        "lives_saved" => $total_saves,
        "total_missions" => $total_missions,
        "success_rate" => $success_rate,
    ],
    "history" => $history
]);
?>
