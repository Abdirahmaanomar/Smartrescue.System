<?php
require_once 'config/db.php';

// Create a test user if not exists
$phone = '1234567890';
$pass = password_hash('password', PASSWORD_BCRYPT);
mysqli_query($conn, "INSERT IGNORE INTO users (id, phone, password) VALUES (999, '$phone', '$pass')");

// Start session manually
session_id('test_session_id');
session_start();
$_SESSION['user_id'] = 999;

// Call user_settings logic
$_POST['action'] = 'toggle_preference';
$_POST['preference'] = 'dark_mode';
$_POST['value'] = 'true';

ob_start();
include 'api/user/user_settings.php';
$output = ob_get_clean();

echo "OUTPUT: \n" . $output;
?>
