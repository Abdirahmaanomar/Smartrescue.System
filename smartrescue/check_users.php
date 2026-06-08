<?php
header("Content-Type: application/json");
require_once 'config/db.php';
$r = mysqli_query($conn, "SELECT id, fullname, phone, role FROM users WHERE role = 'user' LIMIT 5");
$users = [];
while ($row = mysqli_fetch_assoc($r)) $users[] = $row;
echo json_encode($users, JSON_PRETTY_PRINT);
?>
