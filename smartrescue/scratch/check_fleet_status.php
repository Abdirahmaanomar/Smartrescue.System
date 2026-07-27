<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$conn = mysqli_connect('localhost', 'maanka', '1234', 'smartrescuesystem');
if (!$conn) { die("Conn failed: " . mysqli_connect_error()); }
$res = mysqli_query($conn, 'SELECT e.id, e.unit_name, e.unit_type, e.status, e.driver_id, u.fullname FROM emergency_units e LEFT JOIN users u ON e.driver_id = u.id');
$data = mysqli_fetch_all($res, MYSQLI_ASSOC);
echo json_encode($data, JSON_PRETTY_PRINT);
?>
