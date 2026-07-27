<?php
/**
 * Fix: Reset all idle "available" units to "offline"
 * Units that have an active job (pending/accepted/en_route/arrived) stay as-is.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
$conn = mysqli_connect('localhost', 'maanka', '1234', 'smartrescuesystem');
if (!$conn) { die("Conn failed: " . mysqli_connect_error()); }

// Get units that currently have an active rescue request assigned (these stay online)
$active_q = mysqli_query($conn,
    "SELECT DISTINCT assigned_unit_id FROM rescue_requests 
     WHERE status IN ('pending','accepted','en_route','arrived') 
     AND assigned_unit_id IS NOT NULL"
);
$active_ids = [];
while ($r = mysqli_fetch_row($active_q)) {
    $active_ids[] = intval($r[0]);
}

// Set all non-busy-active units to offline
if (!empty($active_ids)) {
    $id_list = implode(',', $active_ids);
    $sql = "UPDATE emergency_units SET status = 'offline' WHERE status = 'available' AND id NOT IN ($id_list)";
} else {
    $sql = "UPDATE emergency_units SET status = 'offline' WHERE status = 'available'";
}

if (mysqli_query($conn, $sql)) {
    echo "Done! " . mysqli_affected_rows($conn) . " unit(s) reset to offline.\n";
} else {
    echo "Error: " . mysqli_error($conn) . "\n";
}

// Show current state
$res = mysqli_query($conn, "SELECT e.id, e.unit_name, e.status, u.fullname FROM emergency_units e LEFT JOIN users u ON e.driver_id = u.id");
$data = mysqli_fetch_all($res, MYSQLI_ASSOC);
echo json_encode($data, JSON_PRETTY_PRINT);
?>
