<?php
/**
 * Lightweight endpoint for sidebar badge count only.
 * Returns just the count of pending requests — NO heavy JOINs.
 * Used by admin/includes/sidebar.php to update the nav badge.
 */
header("Content-Type: application/json");
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'pending' => 0]);
    exit();
}

// Single fast COUNT — no JOINs, uses idx_status index
$res = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM rescue_requests WHERE status = 'pending'");
$row = mysqli_fetch_assoc($res);
$pending = $row ? (int)$row['cnt'] : 0;

echo json_encode(['status' => 'success', 'pending' => $pending]);
?>
