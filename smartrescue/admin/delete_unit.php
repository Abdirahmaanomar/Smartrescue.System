<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

if (isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    $sql = "DELETE FROM emergency_units WHERE id = '$id'";
    mysqli_query($conn, $sql);
}

if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    echo json_encode(['status' => 'success']);
    exit();
}

header("Location: manage_units.php");
exit();
?>
