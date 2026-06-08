<?php
session_start();
require_once '../../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
    exit();
}

$q = "SELECT u.fullname, u.phone, u.medical_info, u.emergency_contacts, u.birth_date, u.gender
      FROM rescue_requests r
      JOIN users u ON r.user_id = u.id
      WHERE r.id = $id LIMIT 1";

$res = mysqli_query($conn, $q);
$user = mysqli_fetch_assoc($res);

if ($user) {
    if (!empty($user['birth_date'])) {
        $dob = new DateTime($user['birth_date']);
        $now = new DateTime();
        $user['age'] = $now->diff($dob)->y;
    }
    echo json_encode(['status' => 'success', 'user' => $user]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'User details not found']);
}
