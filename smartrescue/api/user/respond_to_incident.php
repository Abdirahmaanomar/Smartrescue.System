<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
}

$request_id = isset($_POST['request_id']) ? (int)$_POST['request_id'] : null;
$volunteer_id = isset($_POST['volunteer_id']) ? (int)$_POST['volunteer_id'] : null;
$action = $_POST['action'] ?? ''; // 'accept' or 'cancel'

if (!$request_id || !$volunteer_id || !in_array($action, ['accept', 'cancel'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid input parameters']);
    exit();
}

// Check if request exists
$check_sql = "SELECT status, assigned_unit_id, volunteer_id FROM rescue_requests WHERE id = $request_id LIMIT 1";
$check_res = mysqli_query($conn, $check_sql);

if (mysqli_num_rows($check_res) === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Incident request not found']);
    exit();
}

$req = mysqli_fetch_assoc($check_res);

if ($req['status'] === 'completed' || $req['status'] === 'cancelled') {
    echo json_encode(['status' => 'error', 'message' => 'Incident already resolved or cancelled']);
    exit();
}

if ($action === 'accept') {
    // Check if someone else already volunteering
    if ($req['volunteer_id'] && $req['volunteer_id'] != $volunteer_id) {
        echo json_encode(['status' => 'error', 'message' => 'Another volunteer is already assisting with this incident']);
        exit();
    }

    $update_sql = "UPDATE rescue_requests SET volunteer_id = $volunteer_id";
    // If status is pending, promote to accepted
    if ($req['status'] === 'pending') {
        $update_sql .= ", status = 'accepted'";
    }
    $update_sql .= " WHERE id = $request_id";

    if (mysqli_query($conn, $update_sql)) {
        echo json_encode(['status' => 'success', 'message' => 'Response registered successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database update failed: ' . mysqli_error($conn)]);
    }
} elseif ($action === 'cancel') {
    if ($req['volunteer_id'] != $volunteer_id) {
        echo json_encode(['status' => 'error', 'message' => 'You are not the volunteer assigned to this incident']);
        exit();
    }

    $update_sql = "UPDATE rescue_requests SET volunteer_id = NULL";
    // If status is accepted and no driver is assigned, demote back to pending
    if ($req['status'] === 'accepted' && !$req['assigned_unit_id']) {
        $update_sql .= ", status = 'pending'";
    }
    $update_sql .= " WHERE id = $request_id";

    if (mysqli_query($conn, $update_sql)) {
        echo json_encode(['status' => 'success', 'message' => 'Response cancelled successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database update failed: ' . mysqli_error($conn)]);
    }
}
?>
