<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

if (isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    
    // Unassign from any emergency units before deleting
    $unit_update = "UPDATE emergency_units SET driver_id = NULL WHERE driver_id = '$id'";
    mysqli_query($conn, $unit_update);
    
    // Delete the user record
    $sql = "DELETE FROM users WHERE id = '$id' AND role = 'driver'";
    mysqli_query($conn, $sql);
}

header("Location: team.php");
exit();
?>
