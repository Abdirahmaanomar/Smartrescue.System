<?php
require_once __DIR__ . '/../config/db.php';

$res = mysqli_query($conn, "SELECT id, fullname, email, phone, role FROM users WHERE email='maxamed@gmail.com' OR phone='maxamed@gmail.com'");
while ($row = mysqli_fetch_assoc($res)) {
    echo "ID: {$row['id']} | Name: {$row['fullname']} | Email: {$row['email']} | Phone: {$row['phone']} | Role: {$row['role']}\n";
}
?>
