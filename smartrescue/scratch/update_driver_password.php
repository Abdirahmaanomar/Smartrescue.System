<?php
require_once __DIR__ . '/../config/db.php';

$password_hash = password_hash('responder123', PASSWORD_DEFAULT);
$update_q = "UPDATE users SET password = '$password_hash' WHERE email = 'maxamed@gmail.com'";

if (mysqli_query($conn, $update_q)) {
    echo "Password updated successfully for maxamed@gmail.com to 'responder123'!\n";
} else {
    echo "Error updating password: " . mysqli_error($conn) . "\n";
}
?>
