<?php
header("Content-Type: application/json");
session_start();
require_once '../../config/db.php';

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

$user_filter = $user_id > 0 ? "user_id = $user_id AND " : "";
$user_filter_where = $user_id > 0 ? "WHERE user_id = $user_id" : "";

// Daily Reports: rescue_requests created today
$today_start = date('Y-m-d 00:00:00');
$today_end   = date('Y-m-d 23:59:59');
$res = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM rescue_requests WHERE $user_filter created_at BETWEEN '$today_start' AND '$today_end'");
$daily_reports = (int) mysqli_fetch_assoc($res)['cnt'];

// Weekly SOS: rescue_requests in last 7 days
$week_ago = date('Y-m-d H:i:s', strtotime('-7 days'));
$res = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM rescue_requests WHERE $user_filter created_at >= '$week_ago'");
$weekly_sos = (int) mysqli_fetch_assoc($res)['cnt'];

// Success Rate: completed / total * 100
$res_total = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM rescue_requests $user_filter_where");
$total = (int) mysqli_fetch_assoc($res_total)['cnt'];

$res_done = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM rescue_requests WHERE $user_filter status = 'completed'");
$completed = (int) mysqli_fetch_assoc($res_done)['cnt'];

$success_rate = $total > 0 ? round(($completed / $total) * 100) : 0;

// Avg dispatch time: TIMESTAMPDIFF between created_at and dispatch assigned_at
$res_dispatch = mysqli_query($conn, "
    SELECT AVG(TIMESTAMPDIFF(SECOND, rr.created_at, d.assigned_at)) AS avg_sec
    FROM rescue_requests rr
    JOIN dispatches d ON d.request_id = rr.id
    WHERE d.assigned_at IS NOT NULL AND rr.user_id = $user_id
");
$row = mysqli_fetch_assoc($res_dispatch);
$avg_dispatch_sec = $row['avg_sec'] ? (float) $row['avg_sec'] : 0;
$avg_dispatch_mins = $avg_dispatch_sec > 0 ? round($avg_dispatch_sec / 60, 1) : 0;

// Avg arrival time: TIMESTAMPDIFF between created_at and when status became 'arrived'
// We use dispatches.completed_at as arrival proxy
$res_arrival = mysqli_query($conn, "
    SELECT AVG(TIMESTAMPDIFF(SECOND, rr.created_at, d.completed_at)) AS avg_sec
    FROM rescue_requests rr
    JOIN dispatches d ON d.request_id = rr.id
    WHERE d.completed_at IS NOT NULL AND rr.user_id = $user_id
");
$row2 = mysqli_fetch_assoc($res_arrival);
$avg_arrival_sec = $row2['avg_sec'] ? (float) $row2['avg_sec'] : 0;
$avg_arrival_mins = $avg_arrival_sec > 0 ? round($avg_arrival_sec / 60, 1) : 0;

// Safety Score: based on success rate
if ($success_rate >= 95) $safety_score = 'A+';
elseif ($success_rate >= 90) $safety_score = 'A';
elseif ($success_rate >= 80) $safety_score = 'B+';
elseif ($success_rate >= 70) $safety_score = 'B';
elseif ($success_rate >= 60) $safety_score = 'C';
else $safety_score = 'D';

echo json_encode([
    'success'          => true,
    'daily_reports'    => $daily_reports,
    'weekly_sos'       => $weekly_sos,
    'success_rate'     => $success_rate . '%',
    'safety_score'     => $safety_score,
    'avg_dispatch_mins'=> $avg_dispatch_mins,
    'avg_arrival_mins' => $avg_arrival_mins,
]);
?>
