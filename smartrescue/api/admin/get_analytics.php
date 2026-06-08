<?php
session_start();
require_once '../../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

// Daily counts (last 7 days)
$daily = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $label = date('D', strtotime("-$i days"));
    $r = mysqli_query($conn, "SELECT COUNT(*) as c FROM rescue_requests WHERE DATE(created_at) = '$date'");
    $daily[] = ['date' => $date, 'label' => $label, 'count' => (int)mysqli_fetch_assoc($r)['c']];
}

// Emergency type breakdown
$q_types = mysqli_query($conn, "SELECT emergency_type, COUNT(*) as c FROM rescue_requests GROUP BY emergency_type ORDER BY c DESC LIMIT 8");
$types = [];
while ($row = mysqli_fetch_assoc($q_types)) {
    $types[] = ['type' => $row['emergency_type'] ?: 'Unknown', 'count' => (int)$row['c']];
}

// Overall stats
$r_total     = mysqli_query($conn, "SELECT COUNT(*) as c FROM rescue_requests");
$r_today     = mysqli_query($conn, "SELECT COUNT(*) as c FROM rescue_requests WHERE DATE(created_at) = CURDATE()");
$r_completed = mysqli_query($conn, "SELECT COUNT(*) as c FROM rescue_requests WHERE status='completed'");
$r_avg       = mysqli_query($conn, "SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, updated_at)) as avg_t FROM rescue_requests WHERE status != 'pending'");

$total     = (int)mysqli_fetch_assoc($r_avg)['c'];
$today     = (int)mysqli_fetch_assoc($r_avg)['c'];
$completed = (int)mysqli_fetch_assoc($r_avg)['c'];
$avg_resp  = (int)round((float)(mysqli_fetch_assoc($r_avg)['avg_t'] ?? 0));
$success_rate = $total > 0 ? round(($completed / $total) * 100) : 0;

echo json_encode([
    'status'       => 'success',
    'stats'        => [
        'total'        => $total,
        'today'        => $today,
        'completed'    => $completed,
        'avg_response' => $avg_resp,
        'success_rate' => $success_rate,
    ],
    'daily'        => $daily,
    'types'        => $types,
]);
?>
