<?php
// api/user/get_profile.php
// Returns the current user's fresh profile data from the database.
// Used by the Flutter app to synchronise profile changes made on the website.
session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';

// Accept user_id from session (web cookie) or POST body (Flutter app)
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
    $user['status'] = 'success';
    echo json_encode($user);
} else {
    echo json_encode(['status' => 'error', 'message' => 'User not found']);
}
?>
