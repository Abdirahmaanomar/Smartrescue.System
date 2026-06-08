<?php
session_start();
require_once '../../config/db.php';

// Authorization
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized");
}

/**
 * Simple PHP script to generate a basic SQL export of the system
 */

$tables = [];
$res = mysqli_query($conn, "SHOW TABLES");
while ($row = mysqli_fetch_row($res)) {
    $tables[] = $row[0];
}

$output = "-- SmartRescue System Database Backup\n";
$output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
$output .= "-- --------------------------------------------------\n\n";

foreach ($tables as $table) {
    // 1. Create table structure
    $res = mysqli_query($conn, "SHOW CREATE TABLE `$table` ");
    $row = mysqli_fetch_row($res);
    $output .= "\n\n" . $row[1] . ";\n\n";

    // 2. Export data
    $res = mysqli_query($conn, "SELECT * FROM `$table` ");
    while ($row = mysqli_fetch_assoc($res)) {
        $keys = array_keys($row);
        $vals = array_values($row);
        $escaped_vals = array_map(function($v) use ($conn) {
            if ($v === null) return 'NULL';
            return "'" . mysqli_real_escape_string($conn, $v) . "'";
        }, $vals);
        $output .= "INSERT INTO `$table` (`" . implode("`, `", $keys) . "`) VALUES (" . implode(", ", $escaped_vals) . ");\n";
    }
}

// Log the backup action
require_once '../../includes/functions.php';
log_activity($conn, $_SESSION['user_id'], 'Database Backup', 'Manual SQL dump generated and downloaded.', 'safe');

// Send to browser
$filename = 'smartrescue_backup_' . date('Y-m-d_His') . '.sql';
header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($output));

echo $output;
exit();
