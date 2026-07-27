<?php
session_start();
require_once '../../config/db.php';

$driver_id = null;
if (isset($_REQUEST['driver_id'])) {
    $driver_id = intval($_REQUEST['driver_id']);
} elseif (isset($_SESSION['user_id']) && $_SESSION['role'] === 'driver') {
    $driver_id = intval($_SESSION['user_id']);
}

if (!$driver_id) {
    header('Content-Type: application/json');
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized']));
}

// Log incoming update status request for troubleshooting
file_put_contents('debug_update_status.txt', date('Y-m-d H:i:s') . " - REQUEST: " . print_r($_REQUEST, true) . " - POST: " . print_r($_POST, true) . "\n", FILE_APPEND);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    header('Content-Type: application/json');

    $request_id = mysqli_real_escape_string($conn, $_POST['request_id'] ?? '');
    $unit_id = mysqli_real_escape_string($conn, $_POST['unit_id'] ?? '');
    $action = mysqli_real_escape_string($conn, $_POST['action'] ?? '');

    if (!$request_id || !$unit_id || !$action) {
        echo json_encode(['status' => 'error', 'message' => 'Missing parameters']);
        exit();
    }

    $response = ['status' => 'error', 'message' => 'Invalid action'];

    if ($action === 'accept') {
        $q1 = "UPDATE rescue_requests SET status = 'accepted' WHERE id = '$request_id'";
        if (mysqli_query($conn, $q1))
            $response = ['status' => 'success'];
    } elseif ($action === 'reject') {
        $q1 = "UPDATE rescue_requests SET status = 'pending', assigned_unit_id = NULL WHERE id = '$request_id'";
        $q2 = "UPDATE emergency_units SET status = 'available' WHERE id = '$unit_id'";
        $q3 = "INSERT INTO dispatches (request_id, unit_id, status) VALUES ('$request_id', '$unit_id', 'rejected')";
        if (mysqli_query($conn, $q1) && mysqli_query($conn, $q2) && mysqli_query($conn, $q3))
            $response = ['status' => 'success'];
    } elseif (in_array($action, ['en_route', 'on_the_way', 'start_trip'])) {
        $q1 = "UPDATE rescue_requests SET status = 'en_route' WHERE id = '$request_id'";
        if (mysqli_query($conn, $q1))
            $response = ['status' => 'success'];
    } elseif ($action === 'arrived') {
        $q1 = "UPDATE rescue_requests SET status = 'arrived' WHERE id = '$request_id'";
        if (mysqli_query($conn, $q1))
            $response = ['status' => 'success'];
    } elseif ($action === 'complete') {
        // Get user_id from the request to complete all of their active requests
        $req_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT user_id, emergency_type FROM rescue_requests WHERE id = '$request_id' LIMIT 1"));
        if ($req_row) {
            $uid = $req_row['user_id'];
            $etype = $req_row['emergency_type'];
            
            // Get all assigned units of the user's active requests to free them
            $units_q = mysqli_query($conn, "SELECT assigned_unit_id FROM rescue_requests WHERE user_id = '$uid' AND status NOT IN ('completed', 'cancelled')");
            while ($u_row = mysqli_fetch_assoc($units_q)) {
                $auid = $u_row['assigned_unit_id'];
                if ($auid) {
                    mysqli_query($conn, "UPDATE emergency_units SET status = 'available' WHERE id = '$auid'");
                }
            }
            
            // Complete all active requests for this user
            $q1 = "UPDATE rescue_requests SET status = 'completed' WHERE user_id = '$uid' AND status NOT IN ('completed', 'cancelled')";
            mysqli_query($conn, $q1);
            
            // Also free the current unit just in case
            mysqli_query($conn, "UPDATE emergency_units SET status = 'available' WHERE id = '$unit_id'");

            $notif_title = mysqli_real_escape_string($conn, "✅ Emergency Resolved");
            $notif_msg   = mysqli_real_escape_string($conn, "Your $etype emergency request has been successfully completed. The responder has finished the job. Stay safe!");
            mysqli_query($conn, "INSERT INTO notifications (user_id, title, message, is_read) VALUES ('$uid', '$notif_title', '$notif_msg', 0)");
            
            $response = ['status' => 'success'];
        } else {
            $response = ['status' => 'error', 'message' => 'Request not found'];
        }
    }

    echo json_encode($response);
    exit();
}
?>