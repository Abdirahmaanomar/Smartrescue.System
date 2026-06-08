<?php
session_start();
require_once '../../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

// Recent SOS alerts (last 20)
$q = "SELECT r.id, r.status, r.emergency_type, r.created_at,
             u.fullname as patient_name, u.phone as patient_phone
      FROM rescue_requests r
      JOIN users u ON r.user_id = u.id
      ORDER BY r.created_at DESC LIMIT 20";
$res = mysqli_query($conn, $q);
$alerts = [];
while ($row = mysqli_fetch_assoc($res)) {
    $alerts[] = [
        'id'           => (int)$row['id'],
        'status'       => $row['status'],
        'emergency_type' => $row['emergency_type'],
        'patient_name' => $row['patient_name'],
        'patient_phone'=> $row['patient_phone'],
        'created_at'   => $row['created_at'],
        'is_new'       => $row['status'] === 'pending',
    ];
}

$pending_count = count(array_filter($alerts, fn($a) => $a['status'] === 'pending'));

echo json_encode([
    'status'        => 'success',
    'alerts'        => $alerts,
    'pending_count' => $pending_count,
]);
?>
