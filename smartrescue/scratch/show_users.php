<?php
require_once __DIR__ . '/../config/db.php';

echo "=== USERS ===\n";
$res = mysqli_query($conn, "SELECT id, fullname, role, phone, email FROM users");
while ($r = mysqli_fetch_assoc($res)) {
    echo "ID: {$r['id']} | Name: {$r['fullname']} | Role: {$r['role']} | Phone: {$r['phone']} | Email: {$r['email']}\n";
}

echo "\n=== EMERGENCY UNITS ===\n";
$res = mysqli_query($conn, "SELECT id, unit_name, unit_type, plate_number, status, driver_id FROM emergency_units");
while ($r = mysqli_fetch_assoc($res)) {
    echo "ID: {$r['id']} | Unit: {$r['unit_name']} | Type: {$r['unit_type']} | Plate: {$r['plate_number']} | Status: {$r['status']} | DriverID: {$r['driver_id']}\n";
}

echo "\n=== ACTIVE DISPATCHES ===\n";
$res = mysqli_query($conn, "SELECT id, request_id, unit_id, status FROM dispatches");
while ($r = mysqli_fetch_assoc($res)) {
    echo "ID: {$r['id']} | ReqID: {$r['request_id']} | UnitID: {$r['unit_id']} | Status: {$r['status']}\n";
}

echo "\n=== ACTIVE RESCUE REQUESTS ===\n";
$res = mysqli_query($conn, "SELECT id, user_id, lat, lng, status, assigned_unit_id FROM rescue_requests WHERE status NOT IN ('completed', 'cancelled')");
while ($r = mysqli_fetch_assoc($res)) {
    echo "ID: {$r['id']} | UserID: {$r['user_id']} | Lat/Lng: {$r['lat']},{$r['lng']} | Status: {$r['status']} | AssignedUnitID: {$r['assigned_unit_id']}\n";
}
?>
