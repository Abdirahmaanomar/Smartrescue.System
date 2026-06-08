<?php
/**
 * Admin/Driver API: Update Rescue Request Status
 * 
 * Progresses a rescue request through the pipeline:
 *   pending → accepted → en_route → arrived → completed
 * 
 * POST params:
 *   - request_id : int
 *   - status     : string (accepted|en_route|arrived|completed|cancelled)
 *   - unit_id    : int (optional, required when accepting)
 */
header("Content-Type: application/json");
session_start();
require_once '../../config/db.php';

// Auth check: must be admin or driver
$user_id = $_SESSION['user_id'] ?? null;
$role    = $_SESSION['role'] ?? '';

if (!$user_id || !in_array($role, ['admin', 'driver'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit();
}

$request_id = intval($_POST['request_id'] ?? 0);
$new_status = trim($_POST['status'] ?? '');
$unit_id    = intval($_POST['unit_id'] ?? 0);

// Validate status
$valid_statuses = ['accepted', 'en_route', 'arrived', 'completed', 'cancelled'];
if (!in_array($new_status, $valid_statuses)) {
    echo json_encode(["status" => "error", "message" => "Invalid status: $new_status"]);
    exit();
}

if ($request_id <= 0) {
    echo json_encode(["status" => "error", "message" => "Invalid request_id"]);
    exit();
}

// Get the current request
$req_q = mysqli_query($conn, "SELECT id, status, assigned_unit_id, user_id FROM rescue_requests WHERE id = '$request_id'");
$req = $req_q ? mysqli_fetch_assoc($req_q) : null;

if (!$req) {
    echo json_encode(["status" => "error", "message" => "Request not found"]);
    exit();
}

// Validate status transition
$current = $req['status'];
$valid_transitions = [
    'pending'   => ['accepted', 'cancelled'],
    'accepted'  => ['en_route', 'cancelled'],
    'en_route'  => ['arrived', 'cancelled'],
    'arrived'   => ['completed', 'cancelled'],
];

if (!isset($valid_transitions[$current]) || !in_array($new_status, $valid_transitions[$current])) {
    echo json_encode([
        "status"  => "error",
        "message" => "Cannot transition from '$current' to '$new_status'"
    ]);
    exit();
}

// Begin updates
$queries_ok = true;

// If accepting, assign unit
if ($new_status === 'accepted') {
    if ($unit_id > 0) {
        // Check unit availability
        $unit_q = mysqli_query($conn, "SELECT id, status FROM emergency_units WHERE id = '$unit_id'");
        $unit = $unit_q ? mysqli_fetch_assoc($unit_q) : null;
        
        if (!$unit) {
            echo json_encode(["status" => "error", "message" => "Unit not found"]);
            exit();
        }
        
        // Assign unit and update status
        $q1 = "UPDATE rescue_requests SET status = 'accepted', assigned_unit_id = '$unit_id' WHERE id = '$request_id'";
        $q2 = "UPDATE emergency_units SET status = 'busy' WHERE id = '$unit_id'";
        $queries_ok = mysqli_query($conn, $q1) && mysqli_query($conn, $q2);
    } else {
        // Accept without unit assignment
        $queries_ok = mysqli_query($conn, "UPDATE rescue_requests SET status = 'accepted' WHERE id = '$request_id'");
    }
} elseif ($new_status === 'completed' || $new_status === 'cancelled') {
    $victim_id = $req['user_id'];
    
    // Free all units assigned to the user's active requests
    $units_q = mysqli_query($conn, "SELECT assigned_unit_id FROM rescue_requests WHERE user_id = '$victim_id' AND status NOT IN ('completed', 'cancelled')");
    while ($u_row = mysqli_fetch_assoc($units_q)) {
        $auid = $u_row['assigned_unit_id'];
        if ($auid) {
            mysqli_query($conn, "UPDATE emergency_units SET status = 'available' WHERE id = '$auid'");
        }
    }
    
    // Update all active requests for this user
    $queries_ok = mysqli_query($conn, "UPDATE rescue_requests SET status = '$new_status' WHERE user_id = '$victim_id' AND status NOT IN ('completed', 'cancelled')");
    
    // Create notification for the user
    $notif_title = $new_status === 'completed' ? 'Rescue Completed' : 'Request Cancelled';
    $notif_msg   = $new_status === 'completed' 
        ? 'Your rescue request has been completed. Stay safe!'
        : 'Your rescue request has been cancelled.';
    mysqli_query($conn, "INSERT INTO notifications (user_id, title, message) VALUES ('$victim_id', '$notif_title', '$notif_msg')");
} else {
    // Standard status update (en_route, arrived)
    $queries_ok = mysqli_query($conn, "UPDATE rescue_requests SET status = '$new_status' WHERE id = '$request_id'");
    
    // Create notification for the user
    $victim_id = $req['user_id'];
    $notif_titles = [
        'en_route' => 'Rescue Team En Route',
        'arrived'  => 'Rescue Team Arrived',
    ];
    $notif_msgs = [
        'en_route' => 'A rescue team has been dispatched and is heading to your location!',
        'arrived'  => 'The rescue team has arrived at your location!',
    ];
    $n_title = $notif_titles[$new_status] ?? 'Status Update';
    $n_msg   = $notif_msgs[$new_status] ?? "Your request status has been updated to: $new_status";
    mysqli_query($conn, "INSERT INTO notifications (user_id, title, message) VALUES ('$victim_id', '$n_title', '$n_msg')");
}

if ($queries_ok) {
    echo json_encode([
        "status"  => "success",
        "message" => "Request updated to '$new_status'",
        "request_id" => $request_id,
        "new_status"  => $new_status,
    ]);
} else {
    echo json_encode([
        "status"  => "error",
        "message" => "Database error: " . mysqli_error($conn)
    ]);
}
?>
