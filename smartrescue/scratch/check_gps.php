<?php
require_once __DIR__ . '/../config/db.php';
$res = mysqli_query($conn, "SELECT id, current_lat, current_lng FROM users WHERE id = 1045");
print_r(mysqli_fetch_assoc($res));
