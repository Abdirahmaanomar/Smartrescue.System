<?php
require_once 'config/db.php';
$user_id = 1; // Assuming user 1 exists, let's just test the query syntax
$value = 1;
$query = "UPDATE users SET dark_mode = '$value' WHERE id = '$user_id'";
if (mysqli_query($conn, $query)) {
    echo "Success\n";
} else {
    echo "Error: " . mysqli_error($conn) . "\n";
}
?>
