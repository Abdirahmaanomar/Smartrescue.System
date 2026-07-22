<?php
session_start();
header("Content-Type: application/json");
require_once '../../config/db.php';
require_once '../../includes/functions.php';

$response = array();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = isset($_POST['user_id']) ? clean_input($_POST['user_id'], $conn) : (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null);
    if (!$user_id) {
        echo json_encode(["status" => "error", "message" => "Isticmaalaha lama aqoonsan. Ku noqo login-ka."]);
        exit();
    }
    $lat = isset($_POST['lat']) ? floatval($_POST['lat']) : null;
    $lng = isset($_POST['lng']) ? floatval($_POST['lng']) : null;
    $accuracy = isset($_POST['accuracy']) ? floatval($_POST['accuracy']) : 0;
    
    if ($lat === null || $lng === null || $lat == 0.0 || $lng == 0.0 ||
        $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        echo json_encode(["status" => "error", "message" => "Xogta goobtaada waa khalad (Invalid coordinates). Soo celi GPS-kaaga."]);
        exit();
    }
    
    $type = clean_input($_POST['emergency_type'], $conn);
    $description = isset($_POST['description']) ? clean_input($_POST['description'], $conn) : "";
    $neighborhood = isset($_POST['neighborhood']) ? clean_input($_POST['neighborhood'], $conn) : "";
    

    $image_paths = array();
    $allowed_ext = array('jpg', 'jpeg', 'png', 'gif');
    $upload_dir = '../../uploads/';
    
    // Ensure directory exists
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Loop through uploaded files to handle evidence_image, evidence_image_1, evidence_image_2, etc.
    foreach ($_FILES as $key => $file_info) {
        if (strpos($key, 'evidence_image') === 0 && $file_info['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $file_info['tmp_name'];
            $file_name = $file_info['name'];
            $file_size = $file_info['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            if (in_array($file_ext, $allowed_ext)) {
                if ($file_size <= 5 * 1024 * 1024) { // 5MB limit
                    $new_file_name = "SOS_" . time() . "_" . uniqid() . "." . $file_ext;
                    if (move_uploaded_file($file_tmp, $upload_dir . $new_file_name)) {
                        $image_paths[] = "uploads/" . $new_file_name;
                    } else {
                        $response['status'] = "error";
                        $response['message'] = "Waan ka xunnahay, sawirka lama soo galin karo (Upload failed).";
                        echo json_encode($response);
                        exit();
                    }
                } else {
                    $response['status'] = "error";
                    $response['message'] = "Sawirka waa inuusan ka waynaan 5MB.";
                    echo json_encode($response);
                    exit();
                }
            } else {
                $response['status'] = "error";
                $response['message'] = "Nooca sawirkan ma ahan mid la ogolyahay (Only JPG, PNG, GIF).";
                echo json_encode($response);
                exit();
            }
        }
    }
    $image_path = implode(",", $image_paths);

    // 1. Update the Users table with latest known location
    $update_user_sql = "UPDATE users SET current_lat = '$lat', current_lng = '$lng' WHERE id = '$user_id'";
    mysqli_query($conn, $update_user_sql);

    // 2. Insert SOS Request
    $sql = "INSERT INTO rescue_requests (user_id, lat, lng, accuracy, emergency_type, description, evidence_image, neighborhood, status) 
            VALUES ('$user_id', '$lat', '$lng', '$accuracy', '$type', '$description', '$image_path', '$neighborhood', 'pending')";

    if (mysqli_query($conn, $sql)) {
        $inserted_id = mysqli_insert_id($conn);
        $response['status'] = "success";
        $response['id'] = $inserted_id;
        
        // Fetch user data needed for response messages and subsequent processing
        $user_query = mysqli_query($conn, "SELECT fullname, phone, emergency_contacts, language FROM users WHERE id = '$user_id'");
        $sender_lang = 'en';
        $fullname = 'User';
        $sender_phone = '';
        $emergency_contacts = '';
        if ($user_query && mysqli_num_rows($user_query) > 0) {
            $user_row = mysqli_fetch_assoc($user_query);
            $fullname = $user_row['fullname'] ?? 'User';
            $sender_phone = $user_row['phone'] ?? '';
            $emergency_contacts = $user_row['emergency_contacts'] ?? '';
            $sender_lang = $user_row['language'] ?? 'en';
        }
        
        if ($sender_lang === 'so') {
            $response['message'] = "Gurmadka waa laguu soo diray. Ha ka bixin halka aad joogto!";
        } else {
            $response['message'] = "Emergency dispatch initiated. Please stay calm and remain where you are!";
        }
        
        $alerted_contacts = [];
        if (!empty($emergency_contacts)) {
            $lines = explode("\n", $emergency_contacts);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                $parts = explode(":", $line);
                $c_name = isset($parts[0]) ? trim($parts[0]) : 'Contact';
                $c_phone = isset($parts[1]) ? trim($parts[1]) : '';
                if (!empty($c_phone)) {
                    $alerted_contacts[] = "$c_name ($c_phone)";
                }
            }
            if (!empty($alerted_contacts)) {
                if ($sender_lang === 'so') {
                    $response['message'] .= "\nEhelkaada (" . implode(", ", $alerted_contacts) . ") wargelin SMS ah ayaa loo diray!";
                } else {
                    $response['message'] .= "\nYour emergency contacts (" . implode(", ", $alerted_contacts) . ") have been alerted via SMS!";
                }
            }
        }
        
        // ── INSERT SENDER'S NOTIFICATION IMMEDIATELY (synchronous – instant) ──
        $loc_link_instant = "https://maps.google.com/?q=$lat,$lng";
        if (!empty($neighborhood)) {
            $loc_link_instant .= " ($neighborhood)";
        }
        if (!empty($alerted_contacts)) {
            if ($sender_lang === 'so') {
                $instant_title = "🚨 Codsiga SOS-ka waa la Baahiyay!";
                $instant_msg = "Dalabkaaga gurmadka degdegga ah ee ($type) waa la diray. Eheladaada (" . implode(", ", $alerted_contacts) . ") waxaa loo diray SMS. Goobta: $loc_link_instant";
            } else {
                $instant_title = "🚨 SOS Signal Broadcasted!";
                $instant_msg = "Your emergency SOS ($type) has been sent. Emergency contacts (" . implode(", ", $alerted_contacts) . ") are being alerted. Location: $loc_link_instant";
            }
        } else {
            if ($sender_lang === 'so') {
                $instant_title = "🚨 Codsiga SOS-ka waa la Baahiyay!";
                $instant_msg = "Dalabkaaga gurmadka degdegga ah ee ($type) waa la diray. Kooxaha gurmadka ayaa la ogeysiiyay. Goobta: $loc_link_instant";
            } else {
                $instant_title = "🚨 SOS Signal Broadcasted!";
                $instant_msg = "Your emergency SOS request ($type) has been broadcasted. Emergency response teams have been notified. Location: $loc_link_instant";
            }
        }
        $safe_instant_title = mysqli_real_escape_string($conn, $instant_title);
        $safe_instant_msg   = mysqli_real_escape_string($conn, $instant_msg);
        mysqli_query($conn, "INSERT INTO notifications (user_id, title, message, is_read) VALUES ('$user_id', '$safe_instant_title', '$safe_instant_msg', 0)");

        echo json_encode($response);

        
        // ── LAUNCH BACKGROUND PROCESS ASYNCHRONOUSLY ──
        $php_path = 'php'; // default fallback
        if (stristr(PHP_OS, 'WIN')) {
            // Traverse up to find php/php.exe for XAMPP Windows
            $dir = __DIR__;
            for ($i = 0; $i < 8; $i++) {
                $check = $dir . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'php.exe';
                if (file_exists($check)) {
                    $php_path = $check;
                    break;
                }
                $parent = dirname($dir);
                if ($parent === $dir) {
                    break;
                }
                $dir = $parent;
            }
            if ($php_path === 'php' && file_exists('C:\\xampp\\php\\php.exe')) {
                $php_path = 'C:\\xampp\\php\\php.exe';
            }
            
            $cmd = "start \"\" /B " . escapeshellarg($php_path) . " -f " . escapeshellarg(__DIR__ . "/send_sos_background.php") . " -- " . 
                   escapeshellarg($inserted_id) . " " . 
                   escapeshellarg($user_id) . " " . 
                   escapeshellarg($lat) . " " . 
                   escapeshellarg($lng) . " " . 
                   escapeshellarg($type) . " " . 
                   escapeshellarg($fullname) . " " . 
                   escapeshellarg($sender_phone) . " " . 
                   escapeshellarg($sender_lang) . " " . 
                   escapeshellarg($neighborhood) . " " . 
                   escapeshellarg(base64_encode($emergency_contacts)) . " > NUL 2>&1";
            pclose(popen($cmd, "r"));
        } else {
            $cmd = "php -f " . escapeshellarg(__DIR__ . "/send_sos_background.php") . " -- " . 
                   escapeshellarg($inserted_id) . " " . 
                   escapeshellarg($user_id) . " " . 
                   escapeshellarg($lat) . " " . 
                   escapeshellarg($lng) . " " . 
                   escapeshellarg($type) . " " . 
                   escapeshellarg($fullname) . " " . 
                   escapeshellarg($sender_phone) . " " . 
                   escapeshellarg($sender_lang) . " " . 
                   escapeshellarg($neighborhood) . " " . 
                   escapeshellarg(base64_encode($emergency_contacts)) . " > /dev/null 2>&1 &";
            shell_exec($cmd);
        }
        exit(); // Finish background thread
    } else {
        $response['status'] = "error";
        $response['message'] = "Cilad farsamo: " . mysqli_error($conn);
    }
} else {
    $response['status'] = "error";
    $response['message'] = "Invalid Request";
}

echo json_encode($response);
?>