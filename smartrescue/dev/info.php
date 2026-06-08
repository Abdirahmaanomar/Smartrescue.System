<?php
require_once 'config/db.php';
$units = mysqli_fetch_all(mysqli_query($conn, "SELECT * FROM emergency_units"), MYSQLI_ASSOC);
echo json_encode($units, JSON_PRETTY_PRINT);
?>
