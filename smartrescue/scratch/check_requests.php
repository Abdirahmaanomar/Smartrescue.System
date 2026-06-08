<?php
require_once 'C:/xampp/htdocs/SmartRescueApp/smartrescue/config/db.php';

echo "=== LATEST RESCUE REQUESTS ===\n";
$res = mysqli_query($conn, "SELECT id, user_id, status, assigned_unit_id, emergency_type, created_at FROM rescue_requests ORDER BY id DESC LIMIT 5");
while ($row = mysqli_fetch_assoc($res)) {
    print_r($row);
}

echo "\n=== EMERGENCY UNITS ===\n";
$res2 = mysqli_query($conn, "SELECT id, unit_name, unit_type, status, driver_id FROM emergency_units");
while ($row2 = mysqli_fetch_assoc($res2)) {
    print_r($row2);
}

echo "\n=== DRIVERS ===\n";
$res3 = mysqli_query($conn, "SELECT id, fullname, phone, role FROM users WHERE role = 'driver'");
while ($row3 = mysqli_fetch_assoc($res3)) {
    print_r($row3);
}
?>
