<?php
$conn = mysqli_connect('localhost', 'maanka', '1234', 'smartrescuesystem');
if (!$conn) { die('Connection failed: ' . mysqli_connect_error()); }

$phone = '611226645';
$new_password = 'responder123';

$hash = password_hash($new_password, PASSWORD_BCRYPT);
$result = mysqli_query($conn, "UPDATE users SET password = '$hash' WHERE phone = '$phone'");

if ($result && mysqli_affected_rows($conn) > 0) {
    echo "✓ Password si guul leh ayaa loo cusboonaysiiyay!\n";
    echo "Phone: $phone\n";
    echo "New password: $new_password\n";
    echo "New hash: $hash\n";
    
    // Xaqiiji
    $check = mysqli_query($conn, "SELECT password FROM users WHERE phone = '$phone'");
    $row = mysqli_fetch_assoc($check);
    echo "Xaqiijin: " . (password_verify($new_password, $row['password']) ? "PASS ✓" : "FAIL ✗") . "\n";
} else {
    echo "✗ Wax is bedel ah ma jiro. Hubi haddii phone-ka saxsan yahay.\n";
}
?>
