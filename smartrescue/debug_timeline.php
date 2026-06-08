<?php
header("Content-Type: application/json");
require_once 'config/db.php';

// Show all rescue_requests
$result = mysqli_query($conn, "SELECT id, user_id, status, emergency_type, assigned_unit_id, created_at FROM rescue_requests ORDER BY id DESC LIMIT 10");
$requests = [];
while ($row = mysqli_fetch_assoc($result)) {
    $requests[] = $row;
}

// Show all emergency_units
$result2 = mysqli_query($conn, "SELECT * FROM emergency_units LIMIT 10");
$units = [];
while ($row = mysqli_fetch_assoc($result2)) {
    $units[] = $row;
}

// Show column names for rescue_requests
$cols_result = mysqli_query($conn, "SHOW COLUMNS FROM rescue_requests");
$columns = [];
while ($row = mysqli_fetch_assoc($cols_result)) {
    $columns[] = $row['Field'];
}

echo json_encode([
    'rescue_requests' => $requests,
    'emergency_units' => $units,
    'rescue_request_columns' => $columns,
], JSON_PRETTY_PRINT);
?>
