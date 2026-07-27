<?php
session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'driver') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$driver_id = $_SESSION['user_id'];
$key   = $_POST['key']   ?? '';
$value = $_POST['value'] ?? '';

$allowed_keys = ['dark_mode', 'notifications_enabled', 'language'];

if (!in_array($key, $allowed_keys)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid key']);
    exit();
}

$key   = mysqli_real_escape_string($conn, $key);
$value = mysqli_real_escape_string($conn, $value);

$q = mysqli_query($conn, "UPDATE users SET `$key` = '$value' WHERE id = '$driver_id'");

if ($q) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
}
