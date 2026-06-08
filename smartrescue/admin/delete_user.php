<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

if (isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    
    // Delete the user record
    $sql = "DELETE FROM users WHERE id = '$id' AND role = 'user'";
    mysqli_query($conn, $sql);
}

header("Location: users.php");
exit();
?>
