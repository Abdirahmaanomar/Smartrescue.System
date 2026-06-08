<?php
header("Content-Type: application/json");
session_start();
require_once '../../config/db.php';

$user_id = isset($_REQUEST['user_id']) ? intval($_REQUEST['user_id']) : (isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null);

if (!$user_id) {
    echo json_encode([]);
    exit();
}

// 1. Hubi haddii ay jiraan wax ogeysiisyo ah
$check_sql = "SELECT COUNT(*) as cnt FROM notifications WHERE user_id = '$user_id'";
$check_res = mysqli_query($conn, $check_sql);
$row = mysqli_fetch_assoc($check_res);

if ($row['cnt'] == 0) {
    // Geli ogeysiisyo default ah si meeshu aysan u noqon eber!
    $notifs = [
        [
            'title' => 'Ku soo dhawaada SmartRescue!',
            'message' => 'SmartRescue waa nidaam gurmad oo casri ah. Waxaan ku faraxsanahay inaan ku difaacno oo aan kuu shaqayno.',
        ],
        [
            'title' => 'GPS Live Tracking Active',
            'message' => 'Goobtaada GPS-ka si ammaan ah ayaa loola socdaa xilliyada xaaladda degdegga ah si gurmadku kuugu soo gaaro si degdeg ah.',
        ]
    ];
    foreach ($notifs as $n) {
        $t = mysqli_real_escape_string($conn, $n['title']);
        $m = mysqli_real_escape_string($conn, $n['message']);
        mysqli_query($conn, "INSERT INTO notifications (user_id, title, message, is_read) VALUES ('$user_id', '$t', '$m', 0)");
    }
}

// 2. Soo saar ogeysiisyada
$sql = "SELECT id, title, message, is_read, created_at FROM notifications WHERE user_id = '$user_id' ORDER BY created_at DESC LIMIT 50";
$result = mysqli_query($conn, $sql);
$notifications = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $notifications[] = [
            'id' => (int) $row['id'],
            'title' => $row['title'],
            'message' => $row['message'],
            'is_read' => (int) $row['is_read'] == 1,
            'created_at' => $row['created_at'],
        ];
    }
}

echo json_encode($notifications);
?>
