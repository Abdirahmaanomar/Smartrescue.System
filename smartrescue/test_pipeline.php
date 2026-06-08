<?php
/**
 * Test script: Simulate the full rescue request pipeline.
 * Progresses request #9 through: pending → accepted → en_route → arrived
 * 
 * Usage: ?action=accept|enroute|arrived|completed|reset|status
 */
header("Content-Type: application/json");
require_once 'config/db.php';

$action = $_GET['action'] ?? 'status';

// Find the latest active request
$req_q = mysqli_query($conn, "SELECT id, user_id, status, assigned_unit_id FROM rescue_requests WHERE status NOT IN ('completed','cancelled') ORDER BY id DESC LIMIT 1");
$req = $req_q ? mysqli_fetch_assoc($req_q) : null;

if (!$req && $action !== 'status') {
    echo json_encode(["error" => "No active request found"]);
    exit();
}

$request_id = $req['id'] ?? 0;

switch ($action) {
    case 'accept':
        // Find first available unit
        $unit_q = mysqli_query($conn, "SELECT id FROM emergency_units WHERE status = 'available' LIMIT 1");
        $unit = $unit_q ? mysqli_fetch_assoc($unit_q) : null;
        if (!$unit) {
            echo json_encode(["error" => "No available units"]);
            exit();
        }
        mysqli_query($conn, "UPDATE rescue_requests SET status = 'accepted', assigned_unit_id = '{$unit['id']}' WHERE id = '$request_id'");
        mysqli_query($conn, "UPDATE emergency_units SET status = 'busy' WHERE id = '{$unit['id']}'");
        mysqli_query($conn, "INSERT INTO notifications (user_id, title, message) VALUES ('{$req['user_id']}', 'Request Accepted', 'Your emergency request has been accepted by dispatch.')");
        echo json_encode(["status" => "success", "action" => "accepted", "unit_id" => $unit['id'], "request_id" => $request_id]);
        break;
        
    case 'enroute':
        mysqli_query($conn, "UPDATE rescue_requests SET status = 'en_route' WHERE id = '$request_id'");
        mysqli_query($conn, "INSERT INTO notifications (user_id, title, message) VALUES ('{$req['user_id']}', 'Rescue En Route', 'A rescue team is heading to your location!')");
        echo json_encode(["status" => "success", "action" => "en_route", "request_id" => $request_id]);
        break;
        
    case 'arrived':
        mysqli_query($conn, "UPDATE rescue_requests SET status = 'arrived' WHERE id = '$request_id'");
        mysqli_query($conn, "INSERT INTO notifications (user_id, title, message) VALUES ('{$req['user_id']}', 'Team Arrived', 'The rescue team has arrived at your location!')");
        echo json_encode(["status" => "success", "action" => "arrived", "request_id" => $request_id]);
        break;
        
    case 'completed':
        $unit_id = $req['assigned_unit_id'];
        mysqli_query($conn, "UPDATE rescue_requests SET status = 'completed' WHERE id = '$request_id'");
        if ($unit_id) {
            mysqli_query($conn, "UPDATE emergency_units SET status = 'available' WHERE id = '$unit_id'");
        }
        echo json_encode(["status" => "success", "action" => "completed", "request_id" => $request_id]);
        break;
        
    case 'reset':
        // Reset all requests to pending and free units
        mysqli_query($conn, "UPDATE rescue_requests SET status = 'pending', assigned_unit_id = NULL");
        mysqli_query($conn, "UPDATE emergency_units SET status = 'available'");
        echo json_encode(["status" => "success", "action" => "reset"]);
        break;
        
    case 'status':
    default:
        // Show current state
        $all_q = mysqli_query($conn, "SELECT rr.id, rr.status, rr.user_id, rr.assigned_unit_id, eu.unit_name, eu.plate_number, u.fullname as driver_name
                                       FROM rescue_requests rr
                                       LEFT JOIN emergency_units eu ON eu.id = rr.assigned_unit_id
                                       LEFT JOIN users u ON u.id = eu.driver_id
                                       WHERE rr.status NOT IN ('completed','cancelled')
                                       ORDER BY rr.id DESC LIMIT 5");
        $rows = [];
        while ($r = mysqli_fetch_assoc($all_q)) $rows[] = $r;
        echo json_encode(["active_requests" => $rows], JSON_PRETTY_PRINT);
        break;
}
?>
