<?php
header("Content-Type: application/json");
require_once '../config/db.php';
$r = mysqli_query($conn, "SELECT id, fullname, phone, role FROM users WHERE id = 1015");
echo json_encode($r ? mysqli_fetch_assoc($r) : ["error" => "not found"]);
?>
