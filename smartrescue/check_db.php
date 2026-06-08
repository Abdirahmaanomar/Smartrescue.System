<?php
require_once 'config/db.php';
$res = mysqli_query($conn, "SHOW COLUMNS FROM users");
$cols = [];
while ($row = mysqli_fetch_assoc($res)) {
    $cols[] = $row['Field'];
}
echo implode(", ", $cols);
?>
