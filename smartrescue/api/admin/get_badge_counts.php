<?php
/**
 * Lightweight endpoint for sidebar badge count only.
 * Returns just the count of pending requests — NO heavy JOINs.
 * Used by admin/includes/sidebar.php to update the nav badge.
 * Respects the notif_last_read session timestamp set by Mark All Read.
 */
header("Content-Type: application/json");
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'pending' => 0]);
    exit();
}

// If admin clicked "Mark All Read", only show requests that arrived AFTER that timestamp
$lastRead = $_SESSION['notif_last_read'] ?? null;
if ($lastRead) {
    $safeTs = mysqli_real_escape_string($conn, $lastRead);
    $res = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM rescue_requests WHERE status = 'pending' AND created_at > '$safeTs'");
} else {
    $res = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM rescue_requests WHERE status = 'pending'");
}
$row = mysqli_fetch_assoc($res);
$pending = $row ? (int)$row['cnt'] : 0;

echo json_encode(['status' => 'success', 'pending' => $pending]);
?>
