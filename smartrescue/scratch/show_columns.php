<?php
require_once __DIR__ . '/../config/db.php';

echo "=== COLUMNS OF 'users' TABLE ===\n";
$res = mysqli_query($conn, "SHOW COLUMNS FROM users");
while ($row = mysqli_fetch_assoc($res)) {
    echo "Field: {$row['Field']} | Type: {$row['Type']} | Null: {$row['Null']} | Default: {$row['Default']}\n";
}
?>
