<?php
// Add CORS Headers globally for API access
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
    header("Access-Control-Allow-Credentials: true");
} else {
    header("Access-Control-Allow-Origin: *");
}
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Cookie, X-Session-ID");
error_reporting(0);
ini_set('display_errors', 0);

date_default_timezone_set('Africa/Mogadishu');

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Deegaanka Database-ka
$host = "localhost";
$user = "maanka"; // XAMPP default user
$pass = "1234"; // XAMPP default password
$dbname = "smartrescuesystem";

// Samaynta xiriirka
$conn = mysqli_connect($host, $user, $pass, $dbname);

// Hubinta xiriirka
if (!$conn) {
    error_log("Connection failed: " . mysqli_connect_error());
    die("Raali nogo, cilad farsamo ayaa jirta.");
}

// In xogta loogu diro habka UTF-8
mysqli_set_charset($conn, "utf8mb4");
mysqli_query($conn, "SET time_zone = '+03:00'");

// --- ROBUST SELF-HEALING DATABASE CHECK ---
// 1. Hubi emergency_units
$tables = mysqli_query($conn, "SHOW TABLES LIKE 'emergency_units'");
if (mysqli_num_rows($tables) == 0) {
    mysqli_query($conn, "CREATE TABLE emergency_units (
        id INT AUTO_INCREMENT PRIMARY KEY,
        unit_name VARCHAR(100),
        unit_type VARCHAR(50),
        plate_number VARCHAR(50),
        status VARCHAR(20) DEFAULT 'offline',
        driver_id INT,
        current_lat DECIMAL(10,8),
        current_lng DECIMAL(11,8),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} else {
    $cols = ["unit_name" => "VARCHAR(100)", "unit_type" => "VARCHAR(50)", "plate_number" => "VARCHAR(50)", "status" => "VARCHAR(20) DEFAULT 'offline'", "driver_id" => "INT", "current_lat" => "DECIMAL(10,8)", "current_lng" => "DECIMAL(11,8)"];
    foreach ($cols as $col => $def) {
        $check = mysqli_query($conn, "SHOW COLUMNS FROM emergency_units LIKE '$col'");
        if (mysqli_num_rows($check) == 0)
            mysqli_query($conn, "ALTER TABLE emergency_units ADD COLUMN $col $def");
    }
}

// 2. Hubi rescue_requests
$tables = mysqli_query($conn, "SHOW TABLES LIKE 'rescue_requests'");
if (mysqli_num_rows($tables) == 0) {
    mysqli_query($conn, "CREATE TABLE rescue_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        lat DECIMAL(10,8),
        lng DECIMAL(11,8),
        accuracy FLOAT,
        emergency_type VARCHAR(50),
        status VARCHAR(20) DEFAULT 'pending',
        assigned_unit_id INT,
        description TEXT,
        evidence_image VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} else {
    $cols = [
        "assigned_unit_id" => "INT",
        "description" => "TEXT",
        "evidence_image" => "VARCHAR(255)",
        "accuracy" => "FLOAT",
        "volunteer_id" => "INT NULL",
        "neighborhood" => "VARCHAR(255) NULL",
        "updated_at" => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
    ];
    foreach ($cols as $col => $def) {
        $check = mysqli_query($conn, "SHOW COLUMNS FROM rescue_requests LIKE '$col'");
        if (mysqli_num_rows($check) == 0)
            mysqli_query($conn, "ALTER TABLE rescue_requests ADD COLUMN $col $def");
    }
}
// 3. Hubi users table
$tables = mysqli_query($conn, "SHOW TABLES LIKE 'users'");
if (mysqli_num_rows($tables) > 0) {
    $cols = [
        "profile_image" => "VARCHAR(255)",
        "dark_mode" => "TINYINT(1) DEFAULT 0",
        "medical_info" => "TEXT",
        "emergency_contacts" => "TEXT",
        "last_session_id" => "VARCHAR(255) NULL",
        "current_lat" => "DECIMAL(10,8) NULL",
        "current_lng" => "DECIMAL(11,8) NULL",
        "is_volunteer" => "TINYINT(1) DEFAULT 0"
    ];
    foreach ($cols as $col => $def) {
        $check = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE '$col'");
        if (mysqli_num_rows($check) == 0)
            mysqli_query($conn, "ALTER TABLE users ADD COLUMN $col $def");
    }
}
// 4. Hubi notifications table
$tables = mysqli_query($conn, "SHOW TABLES LIKE 'notifications'");
if (mysqli_num_rows($tables) == 0) {
    mysqli_query($conn, "CREATE TABLE notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
}

// 5. Hubi dispatches
$tables = mysqli_query($conn, "SHOW TABLES LIKE 'dispatches'");
if (mysqli_num_rows($tables) == 0) {
    mysqli_query($conn, "CREATE TABLE dispatches (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_id INT NOT NULL,
        unit_id INT NOT NULL,
        status VARCHAR(50) DEFAULT 'on_the_way',
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        completed_at TIMESTAMP NULL DEFAULT NULL
    )");
}

// 6. Hubi system_settings table
$tables = mysqli_query($conn, "SHOW TABLES LIKE 'system_settings'");
if (mysqli_num_rows($tables) == 0) {
    mysqli_query($conn, "CREATE TABLE system_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
}

// Insert all default settings (ignore if already exist)
$default_settings = [
    'site_name'     => 'SmartRescue',
    'contact_email' => 'admin@smartrescue.so',
    'contact_phone' => '+252 61 000 0000',
    'notif_email'   => '0',
    'notif_sms'     => '0',
    'notif_sound'   => '1',
    'refresh_rate'  => '4',
    'language'      => 'en',
    'auto_backup'   => '0',
    'sms_username'  => '',
    'sms_password'  => '',
    'sms_sender'    => 'SmartRescue',
];
foreach ($default_settings as $key => $default_val) {
    $check_setting = mysqli_query($conn, "SELECT id FROM system_settings WHERE setting_key = '$key'");
    if (mysqli_num_rows($check_setting) == 0) {
        mysqli_query($conn, "INSERT INTO system_settings (setting_key, setting_value) VALUES ('$key', '$default_val')");
    }
}

// 7. Ensure database indexes exist for performance (user_id on rescue_requests and notifications)
$check_index = mysqli_query($conn, "SHOW INDEX FROM rescue_requests WHERE Key_name = 'user_id'");
if ($check_index && mysqli_num_rows($check_index) == 0) {
    mysqli_query($conn, "ALTER TABLE rescue_requests ADD INDEX (user_id)");
}

$check_index = mysqli_query($conn, "SHOW INDEX FROM notifications WHERE Key_name = 'user_id'");
if ($check_index && mysqli_num_rows($check_index) == 0) {
    mysqli_query($conn, "ALTER TABLE notifications ADD INDEX (user_id)");
}

// --- GLOBAL AUTH & SESSION ENFORCEMENT ---
// This ensures that no page can be accessed if a newer session exists on another device.
if (session_status() !== PHP_SESSION_NONE && isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../includes/session_guard.php';
}
?>