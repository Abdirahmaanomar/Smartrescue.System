<?php
session_start();
$_SESSION['user_id'] = 1047;
$_SESSION['role'] = 'driver';

chdir(__DIR__ . '/../api/driver');
ob_start();
require 'get_active_job.php';
$output = ob_get_clean();

echo "--- Response from get_active_job.php ---\n";
echo $output;
echo "\n";
?>
