<?php
require_once __DIR__ . '/../config/db.php';
$query = "UPDATE emergency_units SET status = 'available'";
if (mysqli_query($conn, $query)) {
    echo "All emergency units set to status = available successfully.\n";
} else {
    echo "Error updating units: " . mysqli_error($conn) . "\n";
}
$res = mysqli_query($conn, "SELECT id, unit_name, unit_type, status, driver_id FROM emergency_units");
while ($row = mysqli_fetch_assoc($res)) {
    echo "Unit ID: {$row['id']} | Name: {$row['unit_name']} | Type: {$row['unit_type']} | Status: {$row['status']}\n";
}
