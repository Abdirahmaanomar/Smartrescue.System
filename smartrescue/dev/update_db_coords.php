<?php
/**
 * Developer helper script: Update database records from old coordinates to Jamhuriya coordinates.
 */
header("Content-Type: application/json");
require_once __DIR__ . '/../config/db.php';

$results = [];

// 1. Update all users to Jamhuriya University coordinates
$q1 = "UPDATE users SET current_lat = 2.06667, current_lng = 45.3667";
if (mysqli_query($conn, $q1)) {
    $results['users_updated'] = mysqli_affected_rows($conn);
} else {
    $results['users_error'] = mysqli_error($conn);
}

// 2. Update all rescue requests to Jamhuriya University coordinates
$q2 = "UPDATE rescue_requests SET lat = 2.06667, lng = 45.3667";
if (mysqli_query($conn, $q2)) {
    $results['requests_updated'] = mysqli_affected_rows($conn);
} else {
    $results['requests_error'] = mysqli_error($conn);
}

echo json_encode(["status" => "success", "results" => $results], JSON_PRETTY_PRINT);
?>
