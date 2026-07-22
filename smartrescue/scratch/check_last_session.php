<?php
require_once __DIR__ . '/../config/db.php';
$row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT last_session_id, location_updated_at FROM users WHERE id=1047"));
echo "last_session_id: {$row['last_session_id']}\n";
echo "location_updated_at: {$row['location_updated_at']}\n";
?>
