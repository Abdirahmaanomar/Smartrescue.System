<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';

// Check if user is logged in or user_id is provided
$user_id = $_GET['user_id'] ?? $_POST['user_id'] ?? $_SESSION['user_id'] ?? null;

// Query active, non-completed, non-cancelled rescue requests
$where = "r.status != 'completed' AND r.status != 'cancelled'";
if ($user_id) {
    $user_id_clean = mysqli_real_escape_string($conn, $user_id);
    $where .= " AND r.user_id != '$user_id_clean'";
}

$sql = "
    SELECT 
        r.id,
        r.user_id,
        r.lat,
        r.lng,
        r.accuracy,
        r.emergency_type,
        r.status,
        r.description,
        r.evidence_image,
        r.created_at,
        r.volunteer_id,
        r.assigned_unit_id,
        u.fullname AS victim_name,
        u.phone AS victim_phone,
        vol.fullname AS volunteer_name,
        vol.phone AS volunteer_phone
    FROM rescue_requests r
    INNER JOIN users u ON r.user_id = u.id
    LEFT JOIN users vol ON r.volunteer_id = vol.id
    WHERE $where
    ORDER BY r.created_at DESC
";

$result = mysqli_query($conn, $sql);

if (!$result) {
    echo json_encode(['status' => 'error', 'message' => 'Query failed: ' . mysqli_error($conn)]);
    exit();
}

$incidents = [];
while ($row = mysqli_fetch_assoc($result)) {
    $incidents[] = [
        'id'               => (int)$row['id'],
        'user_id'          => (int)$row['user_id'],
        'lat'              => $row['lat'] ? (float)$row['lat'] : null,
        'lng'              => $row['lng'] ? (float)$row['lng'] : null,
        'accuracy'         => $row['accuracy'] ? (float)$row['accuracy'] : null,
        'emergency_type'   => $row['emergency_type'] ?? 'Emergency',
        'status'           => $row['status'] ?? 'pending',
        'description'      => $row['description'] ?? '',
        'evidence_image'   => $row['evidence_image'] ?? '',
        'created_at'       => $row['created_at'],
        'volunteer_id'     => $row['volunteer_id'] ? (int)$row['volunteer_id'] : null,
        'volunteer_name'   => $row['volunteer_name'] ?? '',
        'volunteer_phone'  => $row['volunteer_phone'] ?? '',
        'assigned_unit_id' => $row['assigned_unit_id'] ? (int)$row['assigned_unit_id'] : null,
        'victim_name'      => $row['victim_name'] ?? '',
        'victim_phone'     => $row['victim_phone'] ?? '',
    ];
}

echo json_encode(['status' => 'success', 'data' => $incidents]);
?>
