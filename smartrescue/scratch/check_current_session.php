<?php
session_start();
require_once '../config/db.php';
echo "SESSION DATA:\n";
print_r($_SESSION);

if (isset($_SESSION['user_id'])) {
    $uid = intval($_SESSION['user_id']);
    $res = mysqli_query($conn, "SELECT id, fullname, email, role FROM users WHERE id = $uid");
    print_r(mysqli_fetch_assoc($res));
    
    $res2 = mysqli_query($conn, "SELECT id, status, assigned_unit_id FROM rescue_requests WHERE user_id = $uid AND status NOT IN ('completed', 'cancelled')");
    echo "ACTIVE REQUESTS FOR LOGGED IN USER:\n";
    while ($r = mysqli_fetch_assoc($res2)) {
        print_r($r);
    }
} else {
    echo "NO USER IS CURRENTLY LOGGED IN PHP SESSION!\n";
}
?>
