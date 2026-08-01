<?php
/**
 * ═══════════════════════════════════════════════════════
 *  SMARTRESCUE — DATABASE MIGRATION / SETUP SCRIPT
 * ═══════════════════════════════════════════════════════
 *  Run this file ONCE after first deployment or after
 *  adding new columns/tables.
 *
 *  URL: http://localhost/SmartRescueApp/smartrescue/config/migrate.php
 *
 *  After running, this file does nothing if already done.
 *  It is SAFE to run multiple times.
 * ═══════════════════════════════════════════════════════
 */

// Direct connection (no session, no CORS needed for CLI/browser setup)
$host   = "kodama.proxy.rlwy.net";
$user   = "root";
$pass   = "fmEkPApaqRsUctKyHMzblBYgYRqOHbqE";
$dbname = "railway";
$port   = "50996";

$conn = mysqli_connect($host, $user, $pass, $dbname, $port);
if (!$conn) {
    die("❌ Connection failed: " . mysqli_connect_error());
}
mysqli_set_charset($conn, "utf8mb4");

$log = [];

function run_sql($conn, $sql, &$log, $desc = '') {
    $result = mysqli_query($conn, $sql);
    $status = $result ? "✅" : "❌ " . mysqli_error($conn);
    $log[] = "$status $desc";
    return $result;
}

// ── 1. emergency_units ──────────────────────────────────────────────────────
run_sql($conn, "CREATE TABLE IF NOT EXISTS emergency_units (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unit_name VARCHAR(100),
    unit_type VARCHAR(50),
    plate_number VARCHAR(50),
    status VARCHAR(20) DEFAULT 'available',
    driver_id INT,
    current_lat DECIMAL(10,8),
    current_lng DECIMAL(11,8),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", $log, "emergency_units table");

$eu_cols = [
    "unit_name"    => "VARCHAR(100)",
    "unit_type"    => "VARCHAR(50)",
    "plate_number" => "VARCHAR(50)",
    "status"       => "VARCHAR(20) DEFAULT 'available'",
    "driver_id"    => "INT",
    "current_lat"  => "DECIMAL(10,8)",
    "current_lng"  => "DECIMAL(11,8)",
];
foreach ($eu_cols as $col => $def) {
    $check = mysqli_query($conn, "SHOW COLUMNS FROM emergency_units LIKE '$col'");
    if (mysqli_num_rows($check) == 0) {
        run_sql($conn, "ALTER TABLE emergency_units ADD COLUMN $col $def", $log, "emergency_units.$col");
    }
}

// ── 2. rescue_requests ──────────────────────────────────────────────────────
run_sql($conn, "CREATE TABLE IF NOT EXISTS rescue_requests (
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
)", $log, "rescue_requests table");

$rr_cols = [
    "assigned_unit_id" => "INT",
    "description"      => "TEXT",
    "evidence_image"   => "VARCHAR(255)",
    "accuracy"         => "FLOAT",
    "volunteer_id"     => "INT NULL",
    "neighborhood"     => "VARCHAR(255) NULL",
    "updated_at"       => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
];
foreach ($rr_cols as $col => $def) {
    $check = mysqli_query($conn, "SHOW COLUMNS FROM rescue_requests LIKE '$col'");
    if (mysqli_num_rows($check) == 0) {
        run_sql($conn, "ALTER TABLE rescue_requests ADD COLUMN $col $def", $log, "rescue_requests.$col");
    }
}

// Add indexes to rescue_requests for faster queries
$check_user_id_idx = mysqli_query($conn, "SHOW INDEX FROM rescue_requests WHERE Key_name = 'idx_user_id'");
if (mysqli_num_rows($check_user_id_idx) == 0) {
    run_sql($conn, "ALTER TABLE rescue_requests ADD INDEX idx_user_id (user_id)", $log, "rescue_requests.user_id index");
}
$check_status_idx = mysqli_query($conn, "SHOW INDEX FROM rescue_requests WHERE Key_name = 'idx_status'");
if (mysqli_num_rows($check_status_idx) == 0) {
    run_sql($conn, "ALTER TABLE rescue_requests ADD INDEX idx_status (status)", $log, "rescue_requests.status index");
}

// ── 3. users ────────────────────────────────────────────────────────────────
$users_exists = mysqli_query($conn, "SHOW TABLES LIKE 'users'");
if (mysqli_num_rows($users_exists) > 0) {
    $u_cols = [
        "profile_image"      => "VARCHAR(255)",
        "dark_mode"          => "TINYINT(1) DEFAULT 0",
        "medical_info"       => "TEXT",
        "emergency_contacts" => "TEXT",
        "last_session_id"    => "VARCHAR(255) NULL",
        "current_lat"        => "DECIMAL(10,8) NULL",
        "current_lng"        => "DECIMAL(11,8) NULL",
        "is_volunteer"       => "TINYINT(1) DEFAULT 0",
    ];
    foreach ($u_cols as $col => $def) {
        $check = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE '$col'");
        if (mysqli_num_rows($check) == 0) {
            run_sql($conn, "ALTER TABLE users ADD COLUMN $col $def", $log, "users.$col");
        }
    }
    
    // Add indexes to users for faster queries
    $check_role_idx = mysqli_query($conn, "SHOW INDEX FROM users WHERE Key_name = 'idx_role'");
    if (mysqli_num_rows($check_role_idx) == 0) {
        run_sql($conn, "ALTER TABLE users ADD INDEX idx_role (role)", $log, "users.role index");
    }
} else {
    $log[] = "⚠️  users table not found — create it via auth/register.php first.";
}

// ── 4. notifications ────────────────────────────────────────────────────────
run_sql($conn, "CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", $log, "notifications table");

// ── 5. dispatches ───────────────────────────────────────────────────────────
run_sql($conn, "CREATE TABLE IF NOT EXISTS dispatches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    unit_id INT NOT NULL,
    status VARCHAR(50) DEFAULT 'on_the_way',
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL DEFAULT NULL
)", $log, "dispatches table");

// ── 6. system_settings ──────────────────────────────────────────────────────
run_sql($conn, "CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)", $log, "system_settings table");

$default_settings = [
    'site_name'               => 'SmartRescue',
    'contact_email'           => 'admin@smartrescue.so',
    'contact_phone'           => '+252 61 000 0000',
    'notif_email'             => '0',
    'notif_sms'               => '0',
    'notif_sound'             => '1',
    'refresh_rate'            => '4',
    'language'                => 'en',
    'auto_backup'             => '0',
    'sms_username'            => '',
    'sms_password'            => '',
    'sms_sender'              => 'SmartRescue',
    'sos_timeout_warn'        => '10',
    'auto_assign_closest'     => '1',
    'max_missions_per_driver' => '1',
    'allow_multi_responders'  => '0',
];
foreach ($default_settings as $key => $val) {
    $k = mysqli_real_escape_string($conn, $key);
    $v = mysqli_real_escape_string($conn, $val);
    $check = mysqli_query($conn, "SELECT id FROM system_settings WHERE setting_key = '$k'");
    if (mysqli_num_rows($check) == 0) {
        run_sql($conn, "INSERT INTO system_settings (setting_key, setting_value) VALUES ('$k', '$v')", $log, "default setting: $key");
    }
}
run_sql($conn, "UPDATE system_settings SET setting_value = '1' WHERE setting_key = 'max_missions_per_driver'", $log, "Set max_missions_per_driver to 1");
run_sql($conn, "UPDATE system_settings SET setting_value = '0' WHERE setting_key = 'debug_mode'", $log, "Set debug_mode to 0");

// ── 7. system_logs ──────────────────────────────────────────────────────────
run_sql($conn, "CREATE TABLE IF NOT EXISTS system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT DEFAULT NULL,
    type VARCHAR(50) DEFAULT 'info',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci", $log, "system_logs table");

// ── 8. blood_donors ──────────────────────────────────────────────────────────
run_sql($conn, "CREATE TABLE IF NOT EXISTS blood_donors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    blood_type VARCHAR(5) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    lat DECIMAL(10,8) NULL,
    lng DECIMAL(11,8) NULL,
    is_available TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", $log, "blood_donors table");

mysqli_close($conn);

// ── Output ──────────────────────────────────────────────────────────────────
$is_cli = (php_sapi_name() === 'cli');
if ($is_cli) {
    echo "SmartRescue Migration\n";
    echo str_repeat("=", 40) . "\n";
    foreach ($log as $line) echo $line . "\n";
    echo "\nDone.\n";
} else {
    echo "<!DOCTYPE html><html><head><title>SmartRescue Migration</title>
    <style>body{font-family:monospace;background:#0f172a;color:#e2e8f0;padding:30px;}
    h1{color:#38bdf8;} .ok{color:#4ade80;} .err{color:#f87171;} .warn{color:#fbbf24;}
    pre{background:#1e293b;padding:20px;border-radius:8px;}</style></head><body>
    <h1>🚑 SmartRescue — Database Migration</h1><pre>";
    foreach ($log as $line) {
        $cls = strpos($line, '❌') !== false ? 'err' : (strpos($line, '⚠️') !== false ? 'warn' : 'ok');
        echo "<span class='$cls'>" . htmlspecialchars($line) . "</span>\n";
    }
    echo "</pre><p style='color:#94a3b8'>Migration complete. You can close this page.</p></body></html>";
}
?>
