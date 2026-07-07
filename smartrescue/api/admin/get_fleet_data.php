<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

// 1. Fetch Active Incidents
// LOCATION PRIORITY: rescue_requests.lat/lng is updated live by user's browser every 5s.
// Use COALESCE to fall back to users.current_lat only if request coords are NULL.
$incidents_query = "SELECT r.*, 
                        u.fullname as patient_name, 
                        u.phone as patient_phone,
                        COALESCE(r.lat, u.current_lat) as user_lat,
                        COALESCE(r.lng, u.current_lng) as user_lng
                    FROM rescue_requests r
                    JOIN users u ON r.user_id = u.id
                    WHERE r.status != 'completed' AND r.status != 'cancelled'
                    ORDER BY r.created_at DESC";
$incidents_res = mysqli_query($conn, $incidents_query);
$incidents = mysqli_fetch_all($incidents_res, MYSQLI_ASSOC);

// 2. Fetch Fleet (Drivers/Units)
// LOCATION PRIORITY: emergency_units.current_lat updated live by driver's browser.
// Fall back to users.current_lat if the unit location is NULL (driver offline/not started).
$units_query = "SELECT e.*, 
                    u.fullname as driver_name, 
                    u.profile_image as driver_image,
                    COALESCE(NULLIF(e.current_lat, 0), u.current_lat) as current_lat,
                    COALESCE(NULLIF(e.current_lng, 0), u.current_lng) as current_lng
                FROM emergency_units e 
                JOIN users u ON e.driver_id = u.id";
$units_res = mysqli_query($conn, $units_query);
$units = mysqli_fetch_all($units_res, MYSQLI_ASSOC);

// 3. All active users with a known current location (removed: not used by map to save database roundtrips)
$all_users = [];

// 4. Stats
$stats = [
    'pending'         => count(array_filter($incidents, fn($i) => $i['status'] === 'pending')),
    'active'          => count(array_filter($incidents, fn($i) => $i['status'] === 'accepted')),
    'units_available' => count(array_filter($units, fn($u) => $u['status'] === 'available'))
];

echo json_encode([
    'status'    => 'success',
    'incidents' => $incidents,
    'units'     => $units,
    'all_users' => $all_users,
    'stats'     => $stats
]);
?>
