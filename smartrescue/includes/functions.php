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
 * Gets a setting value from the system_settings table with request static and session caching.
 */
function get_setting($conn, $key, $default = '') {
    static $settings_cache = null;
    if ($settings_cache !== null) {
        return isset($settings_cache[$key]) ? $settings_cache[$key] : $default;
    }
    
    // Check if session has cache
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    
    if (!isset($_SESSION['_system_settings_cache'])) {
        $_SESSION['_system_settings_cache'] = [];
        $sql = "SELECT setting_key, setting_value FROM system_settings";
        $result = mysqli_query($conn, $sql);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $_SESSION['_system_settings_cache'][$row['setting_key']] = $row['setting_value'];
            }
        }
    }
    
    $settings_cache = $_SESSION['_system_settings_cache'];
    return isset($settings_cache[$key]) ? $settings_cache[$key] : $default;
}

/**
 * Updates or inserts a setting value into the system_settings table.
 */
function update_setting($conn, $key, $value) {
    $key = mysqli_real_escape_string($conn, $key);
    $value = mysqli_real_escape_string($conn, $value);
    
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    if (isset($_SESSION['_system_settings_cache'])) {
        $_SESSION['_system_settings_cache'][$key] = $value;
    }
    
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
    $action  = mysqli_real_escape_string($conn, $action);
    $details = mysqli_real_escape_string($conn, $details);
    $type    = mysqli_real_escape_string($conn, $type);
    // NOTE: system_logs table is created once by config/migrate.php
    $sql = "INSERT INTO system_logs (user_id, action, details, type) VALUES ($user_id, '$action', '$details', '$type')";
    return mysqli_query($conn, $sql);
}

/**
 * Sends a real SMS via Hormuud SMS API.
 * Falls back to logging if API credentials are not configured.
 */
function send_hormuud_sms($conn, $recipient, $message, $user_id = null) {
    // Fetch all 3 SMS credentials in ONE query instead of 3 separate get_setting() calls
    $keys_in = "'sms_username', 'sms_password', 'sms_sender'";
    $res = mysqli_query($conn, "SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ($keys_in)");
    $settings = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $username = $settings['sms_username'] ?? '';
    $password = $settings['sms_password'] ?? '';
    $sender   = $settings['sms_sender']   ?? 'SmartRescue';

    // Sanitize recipient phone number (Hormuud standard: 25261xxxxxxx)
    $clean_phone = preg_replace('/[^0-9]/', '', $recipient);
    if (strlen($clean_phone) === 9 && strpos($clean_phone, '61') === 0) {
        $clean_phone = '252' . $clean_phone;
    } elseif (strlen($clean_phone) === 10 && strpos($clean_phone, '061') === 0) {
        $clean_phone = '252' . substr($clean_phone, 1);
    }

    if (empty($username) || empty($password)) {
        // Simulation mode — no real SMS credentials configured
        log_activity($conn, $user_id, 'SMS Simulated', "Simulated SMS to $recipient: \"$message\"", 'info');
        return true;
    }

    // Build Hormuud API payload
    $url     = 'https://smsapi.hormuud.com/api/sms/Send';
    $payload = json_encode([
        "sender"    => $sender,
        "recipient" => $clean_phone,
        "message"   => $message,
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST,           true);
    curl_setopt($ch, CURLOPT_POSTFIELDS,     $payload);
    curl_setopt($ch, CURLOPT_TIMEOUT,        10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode("$username:$password"),
    ]);

    $response   = curl_exec($ch);
    $http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        log_activity($conn, $user_id, 'SMS Delivery Error', "cURL Error to $recipient: $curl_error", 'error');
        return false;
    }

    if ($http_code === 200 || $http_code === 201) {
        log_activity($conn, $user_id, 'SMS Delivery Sent', "Hormuud SMS to $recipient ($clean_phone): $response", 'info');
        return true;
    } else {
        log_activity($conn, $user_id, 'SMS Delivery Failed', "Hormuud API HTTP $http_code for $recipient. Response: $response", 'error');
        return false;
    }
}

/**
 * Reverse geocodes coordinates (lat, lng) to a friendly neighborhood name.
 * Calls OpenStreetMap Nominatim API in a non-blocking/timed out request.
 */
function php_reverse_geocode($lat, $lng) {
    $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat=" . urlencode($lat) . "&lon=" . urlencode($lng) . "&zoom=18&addressdetails=1";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT,        5);
    curl_setopt($ch, CURLOPT_USERAGENT,      "SmartRescueApp/1.0");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept-Language: en'
    ]);
    
    $response   = curl_exec($ch);
    $http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200 || !$response) {
        return '';
    }
    
    $data = json_decode($response, true);
    if (!$data || !isset($data['address'])) {
        return '';
    }
    
    $a = $data['address'];
    
    // 1. Get neighborhood/suburb
    $area = null;
    $keys = ['neighbourhood', 'suburb', 'quarter', 'district', 'city_district', 'city', 'town', 'village'];
    foreach ($keys as $k) {
        if (isset($a[$k])) {
            $area = $a[$k];
            break;
        }
    }
    $neighborhoodName = $area ? strval($area) : '';
    
    // 2. Get specific landmark/amenity (any key not in the ignore list)
    $ignoreKeys = [
        'road', 'street', 'house_number', 'house_name', 'postcode', 'country', 
        'country_code', 'state', 'county', 'city', 'town', 'village', 'municipality', 
        'city_district', 'district', 'quarter', 'suburb', 'neighbourhood', 'subdivision', 
        'region', 'state_district', 'ISO3166-2-lvl4'
    ];
    
    $landmarkName = '';
    foreach ($a as $key => $val) {
        if (in_array($key, $ignoreKeys)) continue;
        if ($val !== null && strtolower(strval($val)) !== 'yes' && strtolower(strval($val)) !== 'no') {
            $landmarkName = strval($val);
            break;
        }
    }
    
    if (!empty($neighborhoodName) && !empty($landmarkName)) {
        return "$neighborhoodName (U dhow $landmarkName)";
    } elseif (!empty($neighborhoodName)) {
        return $neighborhoodName;
    } elseif (!empty($landmarkName)) {
        return $landmarkName;
    } else {
        $display = isset($data['display_name']) ? strval($data['display_name']) : '';
        if (!empty($display)) {
            $parts = explode(',', $display);
            return trim($parts[0]);
        }
        return '';
    }
}
?>