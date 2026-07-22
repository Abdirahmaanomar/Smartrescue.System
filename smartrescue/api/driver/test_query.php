<?php
header('Content-Type: application/json');
require_once '../../config/db.php';

$unit_id = 1; // test unit id
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

$res = mysqli_query($conn, $sql);
if (!$res) {
    echo json_encode(['status' => 'error', 'error' => mysqli_error($conn)]);
} else {
    $rows = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $rows[] = $row;
    }
    echo json_encode(['status' => 'success', 'count' => count($rows), 'rows' => $rows]);
}
?>
