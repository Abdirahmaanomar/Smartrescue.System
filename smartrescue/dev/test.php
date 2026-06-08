<?php
require_once 'config/db.php';
$schema = [];
$res = mysqli_query($conn, "DESCRIBE emergency_units");
while($row = mysqli_fetch_assoc($res)){
    $schema[] = $row;
}
echo json_encode($schema, JSON_PRETTY_PRINT);
?>
