<?php
header("Content-Type: application/json");
require_once '../../config/db.php';

$requests = $db->getPendingRequests();

foreach ($requests as &$row) {
    $row['time_formatted'] = date('h:i A', strtotime($row['created_at']));
}

echo json_encode(array(
    "status" => "success",
    "count" => count($requests),
    "requests" => $requests
));
?>
