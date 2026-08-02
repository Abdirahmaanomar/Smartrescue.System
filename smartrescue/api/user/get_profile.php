<?php
// api/user/get_profile.php
// Returns the current user's fresh profile data from the database.
// Used by the Flutter app and website to synchronise profile and driver unit changes.
session_start();
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
require_once '../../config/db.php';

// Accept user_id from session (web cookie), POST, or GET
$user_id = $_SESSION['user_id'] ?? $_POST['user_id'] ?? $_GET['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$user_id = intval($user_id);

$res = mysqli_query($conn,
    "SELECT id, fullname, phone, email, role, profile_image,
            dark_mode, current_lat, current_lng,
            medical_info, emergency_contacts, language, is_volunteer,
            notifications_enabled, vibration_enabled, gps_enabled,
            share_live_location, location_history, gps_access, live_sos_location
     FROM users
     WHERE id = '$user_id'
     LIMIT 1"
);

if ($res && mysqli_num_rows($res) > 0) {
    $user = mysqli_fetch_assoc($res);

    // If the user is a driver, include their real-time emergency unit and mission statistics from DB
    if (strtolower($user['role'] ?? '') === 'driver') {
        $unit_q = mysqli_query($conn, "SELECT * FROM emergency_units WHERE driver_id = '$user_id' LIMIT 1");
        if ($unit_q && mysqli_num_rows($unit_q) > 0) {
            $unit = mysqli_fetch_assoc($unit_q);
            $unit_id = (int)$unit['id'];
            
            $saves_q = mysqli_query($conn, "SELECT COUNT(*) c FROM rescue_requests WHERE assigned_unit_id='$unit_id' AND status='completed'");
            $total_saves = (int)(mysqli_fetch_assoc($saves_q)['c'] ?? 0);
            
            $missions_q = mysqli_query($conn, "SELECT COUNT(*) c FROM rescue_requests WHERE assigned_unit_id='$unit_id'");
            $total_missions = (int)(mysqli_fetch_assoc($missions_q)['c'] ?? 0);

            $rank = 'Rookie Responder';
            if ($total_saves >= 50) $rank = 'Elite Responder';
            elseif ($total_saves >= 20) $rank = 'Senior Responder';
            elseif ($total_saves >= 10) $rank = 'Expert Responder';
            elseif ($total_saves >= 5)  $rank = 'Skilled Responder';

            $user['unit_id']        = $unit_id;
            $user['unit_name']      = $unit['unit_name'];
            $user['unit_type']      = $unit['unit_type'];
            $user['plate_number']   = $unit['plate_number'];
            $user['unit_status']    = $unit['status'];
            $user['status_available'] = (strtolower($unit['status']) === 'available');
            $user['lives_saved']    = $total_saves;
            $user['total_missions'] = $total_missions;
            $user['rank']           = $rank;
            $user['unit']           = [
                'id'             => $unit_id,
                'unit_name'      => $unit['unit_name'],
                'unit_type'      => $unit['unit_type'],
                'plate_number'   => $unit['plate_number'],
                'status'         => $unit['status'],
                'lives_saved'    => $total_saves,
                'total_missions' => $total_missions,
                'rank'           => $rank,
            ];
        }
    }

    $user['status'] = 'success';
    echo json_encode($user);
} else {
    echo json_encode(['status' => 'error', 'message' => 'User not found']);
}
?>
