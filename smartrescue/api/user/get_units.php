<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
}

$status = isset($_GET['status']) && !empty($_GET['status']) ? $_GET['status'] : null;
$units = $db->getEmergencyUnits($status);

foreach ($units as &$row) {
    $row['current_lat'] = $row['current_lat'] ? (float)$row['current_lat'] : null;
    $row['current_lng'] = $row['current_lng'] ? (float)$row['current_lng'] : null;
}

echo json_encode(['status' => 'success', 'data' => $units]);
?>
