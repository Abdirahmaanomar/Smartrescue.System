<?php
/**
 * Admin API: Get all rescue requests with details
 * 
 * Returns a JSON array of rescue requests for admin management.
 * Can filter by status: ?status=pending or ?status=active (non-completed/cancelled)
 */
header("Content-Type: application/json");
session_start();
require_once '../../config/db.php';

// Auth check
$user_id = $_SESSION['user_id'] ?? null;
$role    = $_SESSION['role'] ?? '';

if (!$user_id || $role !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

$filter = $_GET['status'] ?? 'active';

if ($filter === 'active') {
    $where = "rr.status NOT IN ('completed', 'cancelled')";
} elseif ($filter === 'all') {
    $where = "1=1";
} else {
    $safe_filter = mysqli_real_escape_string($conn, $filter);
    $where = "rr.status = '$safe_filter'";
}

$sql = "SELECT 
            rr.id,
            rr.user_id,
            rr.lat,
            rr.lng,
            rr.emergency_type,
            rr.status,
            rr.description,
            rr.evidence_image,
            rr.created_at,
            rr.assigned_unit_id,
            u.fullname AS victim_name,
            u.phone AS victim_phone,
            eu.unit_name,
            eu.unit_type,
            eu.plate_number,
            du.fullname AS driver_name
        FROM rescue_requests rr
        LEFT JOIN users u ON u.id = rr.user_id
        LEFT JOIN emergency_units eu ON eu.id = rr.assigned_unit_id
        LEFT JOIN users du ON du.id = eu.driver_id
        WHERE $where
        ORDER BY rr.created_at DESC
        LIMIT 50";

$result = mysqli_query($conn, $sql);
$requests = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $requests[] = [
            "id"              => (int)$row['id'],
            "user_id"         => (int)$row['user_id'],
            "lat"             => (float)$row['lat'],
            "lng"             => (float)$row['lng'],
            "emergency_type"  => $row['emergency_type'],
            "status"          => $row['status'],
            "description"     => $row['description'] ?? '',
            "evidence_image"  => $row['evidence_image'] ?? '',
            "created_at"      => $row['created_at'],
            "assigned_unit_id"=> $row['assigned_unit_id'] ? (int)$row['assigned_unit_id'] : null,
            "victim_name"     => $row['victim_name'] ?? 'Unknown',
            "victim_phone"    => $row['victim_phone'] ?? '',
            "unit_name"       => $row['unit_name'] ?? '',
            "unit_type"       => $row['unit_type'] ?? '',
            "plate_number"    => $row['plate_number'] ?? '',
            "driver_name"     => $row['driver_name'] ?? '',
        ];
    }
}

echo json_encode($requests);
?>
