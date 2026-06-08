<?php
session_start();
$_SESSION['user_id'] = 3;
$_SESSION['role'] = 'admin';
require_once '../config/db.php';

echo "Database connected successfully.\n";

$q_total = mysqli_query($conn, "SELECT COUNT(*) as c FROM rescue_requests");
if (!$q_total) {
    echo "ERROR (total): " . mysqli_error($conn) . "\n";
} else {
    echo "Total requests: " . mysqli_fetch_assoc($q_total)['c'] . "\n";
}

$q_today = mysqli_query($conn, "SELECT COUNT(*) as c FROM rescue_requests WHERE DATE(created_at) = CURDATE()");
if (!$q_today) {
    echo "ERROR (today): " . mysqli_error($conn) . "\n";
} else {
    echo "Today's requests: " . mysqli_fetch_assoc($q_today)['c'] . "\n";
}

$q_completed = mysqli_query($conn, "SELECT COUNT(*) as c FROM rescue_requests WHERE status='completed'");
if (!$q_completed) {
    echo "ERROR (completed): " . mysqli_error($conn) . "\n";
} else {
    echo "Completed requests: " . mysqli_fetch_assoc($q_completed)['c'] . "\n";
}

$q_avg = mysqli_query($conn, "SELECT AVG(TIMESTAMPDIFF(MINUTE, r.created_at, d.assigned_at)) as avg_t
    FROM rescue_requests r
    JOIN dispatches d ON d.request_id = r.id
    WHERE r.status != 'pending'");
if (!$q_avg) {
    echo "ERROR (avg response): " . mysqli_error($conn) . "\n";
} else {
    $avg_val = mysqli_fetch_assoc($q_avg)['avg_t'];
    echo "Avg response (raw): " . ($avg_val === null ? 'NULL' : $avg_val) . "\n";
}

$daily = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $label = date('D', strtotime("-$i days"));
    $r = mysqli_query($conn, "SELECT COUNT(*) as c FROM rescue_requests WHERE DATE(created_at) = '$date'");
    if (!$r) {
        echo "ERROR (daily $label): " . mysqli_error($conn) . "\n";
    } else {
        echo "Daily $label: " . mysqli_fetch_assoc($r)['c'] . "\n";
    }
}

$q_types = mysqli_query($conn, "SELECT emergency_type, COUNT(*) as c FROM rescue_requests GROUP BY emergency_type ORDER BY c DESC LIMIT 6");
if (!$q_types) {
    echo "ERROR (types): " . mysqli_error($conn) . "\n";
} else {
    echo "Types found:\n";
    while ($row = mysqli_fetch_assoc($q_types)) {
        echo " - " . ($row['emergency_type'] ?: 'Unknown') . ": " . $row['c'] . "\n";
    }
}
?>
