<?php
// Function loogu talagalay in xogta laga ilaaliyo Hackers-ka (SQL Injection)
function clean_input($data, $conn) {
    return mysqli_real_escape_string($conn, htmlspecialchars(strip_tags($data)));
}

// Function-ka xisaabiya masaafada u dhaxaysa labo dhibcood oo GPS ah
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371; // Kilometers
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return round($earth_radius * $c, 2); // Waxay soo celinaysaa KM (Tusaale: 1.5 KM)
}

/**
 * Gets a setting value from the system_settings table.
 */
function get_setting($conn, $key, $default = '') {
    $key = mysqli_real_escape_string($conn, $key);
    $sql = "SELECT setting_value FROM system_settings WHERE setting_key = '$key' LIMIT 1";
    $result = mysqli_query($conn, $sql);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return $row['setting_value'];
    }
    return $default;
}

/**
 * Updates or inserts a setting value into the system_settings table.
 */
function update_setting($conn, $key, $value) {
    $key = mysqli_real_escape_string($conn, $key);
    $value = mysqli_real_escape_string($conn, $value);
    
    // Check if exists
    $check = mysqli_query($conn, "SELECT id FROM system_settings WHERE setting_key = '$key'");
    if (mysqli_num_rows($check) > 0) {
        return mysqli_query($conn, "UPDATE system_settings SET setting_value = '$value' WHERE setting_key = '$key'");
    } else {
        return mysqli_query($conn, "INSERT INTO system_settings (setting_key, setting_value) VALUES ('$key', '$value')");
    }
}

/**
 * Logs a system activity.
 */
function log_activity($conn, $user_id, $action, $details = '', $type = 'info') {
    $user_id = $user_id ? intval($user_id) : 'NULL';
    $action = mysqli_real_escape_string($conn, $action);
    $details = mysqli_real_escape_string($conn, $details);
    $type = mysqli_real_escape_string($conn, $type);
    
    // Ensure table exists (Safe check for first run)
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `system_logs` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) DEFAULT NULL,
        `action` varchar(255) NOT NULL,
        `details` text DEFAULT NULL,
        `type` varchar(50) DEFAULT 'info',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

    $sql = "INSERT INTO system_logs (user_id, action, details, type) VALUES ($user_id, '$action', '$details', '$type')";
    return mysqli_query($conn, $sql);
}

/**
 * Sends a real SMS via Hormuud SMS API.
 * Falls back to logging if API credentials are not configured.
 */
function send_hormuud_sms($conn, $recipient, $message, $user_id = null) {
    // 1. Fetch credentials from system_settings
    $username = get_setting($conn, 'sms_username', '');
    $password = get_setting($conn, 'sms_password', '');
    $sender = get_setting($conn, 'sms_sender', 'SmartRescue');
    
    // Sanitize recipient phone number.
    // If it starts with 0 or 61 or +252, format to include country code without "+" sign (Hormuud standard: 25261xxxxxxx)
    $clean_phone = preg_replace('/[^0-9]/', '', $recipient);
    if (strlen($clean_phone) === 9 && strpos($clean_phone, '61') === 0) {
        $clean_phone = '252' . $clean_phone;
    } elseif (strlen($clean_phone) === 10 && strpos($clean_phone, '061') === 0) {
        $clean_phone = '252' . substr($clean_phone, 1);
    } elseif (strpos($clean_phone, '25261') === 0 && strlen($clean_phone) === 12) {
        // perfect
    }
    
    if (empty($username) || empty($password)) {
        // Fallback to simulation mode
        log_activity($conn, $user_id, 'SMS Simulated', "Simulated SMS to $recipient (Hormuud Credentials not set): \"$message\"", 'info');
        return true;
    }
    
    // 2. Build Hormuud API payload
    $url = 'https://smsapi.hormuud.com/api/sms/Send';
    $payload = json_encode([
        "sender" => $sender,
        "recipient" => $clean_phone,
        "message" => $message
    ]);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode("$username:$password")
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        log_activity($conn, $user_id, 'SMS Delivery Error', "cURL Error sending SMS to $recipient: $curl_error", 'error');
        return false;
    }
    
    if ($http_code === 200 || $http_code === 201) {
        log_activity($conn, $user_id, 'SMS Delivery Sent', "Hormuud SMS delivered to $recipient ($clean_phone): Response: $response", 'info');
        return true;
    } else {
        log_activity($conn, $user_id, 'SMS Delivery Failed', "Hormuud API HTTP $http_code for $recipient. Response: $response", 'error');
        return false;
    }
}
?>