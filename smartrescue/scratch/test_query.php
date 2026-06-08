<?php
require_once 'C:/xampp/htdocs/SmartRescueApp/smartrescue/config/db.php';
$user_id = 1045;
$sql = "SELECT 
            rr.id,
            rr.status
        FROM rescue_requests rr
        LEFT JOIN emergency_units eu ON eu.id = rr.assigned_unit_id
        LEFT JOIN users u ON u.id = eu.driver_id
        WHERE rr.user_id = '$user_id'
          AND (
              rr.status NOT IN ('completed', 'cancelled')
              OR (rr.status = 'completed' AND rr.updated_at >= NOW() - INTERVAL 2 MINUTE)
          )
        ORDER BY rr.created_at DESC
        LIMIT 1";

$result = mysqli_query($conn, $sql);
if (!$result) {
    echo "SQL ERROR: " . mysqli_error($conn) . "\n";
} else {
    echo "SUCCESS! " . mysqli_num_rows($result) . " rows returned.\n";
    print_r(mysqli_fetch_assoc($result));
}
?>
