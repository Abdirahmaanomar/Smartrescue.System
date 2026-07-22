<?php
$conn = mysqli_connect('localhost', 'maanka', '1234', 'smartrescuesystem');
if (!$conn) { die('Connection failed: ' . mysqli_connect_error()); }

$phone = '611226645';
$password_to_test = 'responder123';

$result = mysqli_query($conn, "SELECT id, fullname, phone, email, role, password FROM users WHERE phone = '$phone' OR email = '$phone' LIMIT 1");

if (mysqli_num_rows($result) > 0) {
    $user = mysqli_fetch_assoc($result);
    echo "Found user: " . $user['fullname'] . "\n";
    echo "Role: " . $user['role'] . "\n";
    echo "Phone: " . $user['phone'] . "\n";
    echo "Password hash in DB: " . $user['password'] . "\n";
    echo "password_verify('$password_to_test'): ";
    echo password_verify($password_to_test, $user['password']) ? "PASS ✓ - Password correct!" : "FAIL ✗ - Wrong password!";
    echo "\n";
    
    // Also try generating a fresh hash
    $newHash = password_hash($password_to_test, PASSWORD_BCRYPT);
    echo "New hash would be: " . $newHash . "\n";
} else {
    echo "No user found with phone/email: $phone\n";
    
    // List all users
    $all = mysqli_query($conn, "SELECT id, fullname, phone, email, role FROM users LIMIT 10");
    echo "\nAll users in DB:\n";
    while ($row = mysqli_fetch_assoc($all)) {
        echo "  ID:{$row['id']} | {$row['fullname']} | Phone:{$row['phone']} | Email:{$row['email']} | Role:{$row['role']}\n";
    }
}
?>
