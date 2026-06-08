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
    $sql = "INSERT INTO rescue_requests (user_id, lat, lng, accuracy, emergency_type, description, evidence_image, status) 
            VALUES ('$user_id', '$lat', '$lng', '$accuracy', '$type', '$description', '$image_path', 'pending')";

    if (mysqli_query($conn, $sql)) {
        $response['status'] = "success";
        
        // 3. Notify Emergency Contacts (Simulated SMS & In-app Notification)
        $user_query = mysqli_query($conn, "SELECT fullname, phone, emergency_contacts, language FROM users WHERE id = '$user_id'");
        $sender_lang = 'en';
        if ($user_query && mysqli_num_rows($user_query) > 0) {
            $user_row = mysqli_fetch_assoc($user_query);
            $fullname = $user_row['fullname'] ?? 'User';
            $sender_phone = $user_row['phone'] ?? '';
            $emergency_contacts = $user_row['emergency_contacts'] ?? '';
            $sender_lang = $user_row['language'] ?? 'en';
            
            if ($sender_lang === 'so') {
                $response['message'] = "Gurmadka waa laguu soo diray. Ha ka bixin halka aad joogto!";
            } else {
                $response['message'] = "Emergency dispatch initiated. Please stay calm and remain where you are!";
            }
            
            if (!empty($emergency_contacts)) {
                $lines = explode("\n", $emergency_contacts);
                $alerted_contacts = [];
                $mutual_contacts_names = []; // Track mutual trusted contacts
                
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;
                    
                    $parts = explode(":", $line);
                    $c_name = isset($parts[0]) ? trim($parts[0]) : 'Contact';
                    $c_phone = isset($parts[1]) ? trim($parts[1]) : '';
                    $c_relation = isset($parts[2]) ? trim($parts[2]) : 'Family';
                    
                    if (!empty($c_phone)) {
                        // Construct emergency message with maps location link
                        $alert_msg = "SmartRescue SOS Alert: " . $fullname . " has sent an emergency SOS request (" . $type . "). Please check on them immediately. Location: https://maps.google.com/?q=" . $lat . "," . $lng;
                        
                        // Send SMS via Hormuud Gateway helper (will fall back to simulation logs if credentials aren't set)
                        send_hormuud_sms($conn, $c_phone, $alert_msg, $user_id);
                        
                        // In-App Alert: Check if this emergency contact is a registered user in our app
                        $clean_contact_phone = preg_replace('/[^0-9]/', '', $c_phone);
                        // Match either exact phone or last 9 digits (to ignore country code variations)
                        $last9 = substr($clean_contact_phone, -9);
                        $contact_user_q = mysqli_query($conn, 
                            "SELECT id, fullname, language FROM users WHERE 
                            REPLACE(REPLACE(REPLACE(phone, '+', ''), '-', ''), ' ', '') = '$clean_contact_phone'
                            OR RIGHT(REPLACE(REPLACE(REPLACE(phone, '+', ''), '-', ''), ' ', ''), 9) = '$last9'
                            LIMIT 1"
                        );
                        
                        if ($contact_user_q && mysqli_num_rows($contact_user_q) > 0) {
                            $contact_user = mysqli_fetch_assoc($contact_user_q);
                            $contact_user_id = $contact_user['id'];
                            $contact_lang = $contact_user['language'] ?? 'en';
                            
                            // Only notify if it's not the same user sending the SOS
                            if ($contact_user_id != $user_id) {
                                // Find if this contact has ALSO added the current user to their emergency contacts (mutual trust)
                                $is_mutual = false;
                                if (!empty($sender_phone)) {
                                    $contact_detail_q = mysqli_query($conn, "SELECT emergency_contacts FROM users WHERE id = '$contact_user_id' LIMIT 1");
                                    if ($contact_detail_q && mysqli_num_rows($contact_detail_q) > 0) {
                                        $contact_detail = mysqli_fetch_assoc($contact_detail_q);
                                        $contact_ecs = $contact_detail['emergency_contacts'] ?? '';
                                        
                                        if (!empty($contact_ecs)) {
                                            $clean_sender = preg_replace('/[^0-9]/', '', $sender_phone);
                                            $sender_last9 = substr($clean_sender, -9);
                                            
                                            $contact_lines = explode("\n", $contact_ecs);
                                            foreach ($contact_lines as $c_line) {
                                                $c_line = trim($c_line);
                                                if (empty($c_line)) continue;
                                                $c_parts = explode(":", $c_line);
                                                if (isset($c_parts[1])) {
                                                    $clean_c_part = preg_replace('/[^0-9]/', '', $c_parts[1]);
                                                    $c_part_last9 = substr($clean_c_part, -9);
                                                    if ($sender_last9 === $c_part_last9 && !empty($sender_last9)) {
                                                        $is_mutual = true;
                                                        break;
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }

                                if ($contact_lang === 'so') {
                                    if ($is_mutual) {
                                        $in_app_title = "🚨 Gurmadka Ehelka!";
                                        $in_app_msg = "Ehelkaaga aad aamintay $fullname ayaa dalbaday SOS degdeg ah ($type)! Fadlan caawi hadda. Goobta: https://maps.google.com/?q=$lat,$lng";
                                    } else {
                                        $in_app_title = "🚨 Ogeysiis SOS Ehelka!";
                                        $in_app_msg = "$fullname ($c_relation) waxay ku jiraan xaalad degdeg ah ($type)! Fadlan caawi ama hubi hadda. Goobta: https://maps.google.com/?q=$lat,$lng";
                                    }
                                } else {
                                    if ($is_mutual) {
                                        $in_app_title = "🚨 Trusted Contact SOS Alert!";
                                        $in_app_msg = "Your trusted contact $fullname has triggered an emergency SOS ($type)! Please help now. Location: https://maps.google.com/?q=$lat,$lng";
                                    } else {
                                        $in_app_title = "🚨 Emergency SOS Alert!";
                                        $in_app_msg = "$fullname ($c_relation) is in an emergency situation ($type)! Please help or check on them now. Location: https://maps.google.com/?q=$lat,$lng";
                                    }
                                }
                                
                                $safe_in_app_title = mysqli_real_escape_string($conn, $in_app_title);
                                $safe_in_app_msg = mysqli_real_escape_string($conn, $in_app_msg);
                                mysqli_query($conn, "INSERT INTO notifications (user_id, title, message, is_read) VALUES ('$contact_user_id', '$safe_in_app_title', '$safe_in_app_msg', 0)");
                            }
                        }
                        
                        $alerted_contacts[] = "$c_name ($c_phone)";
                    }
                }
                
                if (!empty($alerted_contacts)) {
                    // If we have mutual (trusted) contacts, give sender a Gurmadka Ehelka-style notification too
                    if ($sender_lang === 'so') {
                        if (!empty($mutual_contacts_names)) {
                            $mutual_list = implode(", ", $mutual_contacts_names);
                            $notif_title = "✅ Gurmadka Ehelkaaga La Ogaysiiyay!";
                            $notif_msg = "Ehelkaada aaminsan $mutual_list ayaa ogeysiis degdeg ah ka helay SOS-kaaga ($type). Waxay kuu ogaayaan halka aad joogto. Goobta: https://maps.google.com/?q=$lat,$lng";
                        } else {
                            $notif_title = "🚨 Eheladaada Gurmadka waa la Wargeliyay!";
                            $notif_msg = "Eheladaada gurmadka ee (" . implode(", ", $alerted_contacts) . ") waxaa loo diray SMS wargelin ah oo ku saabsan SOS-kaaga ($type).";
                        }
                    } else {
                        if (!empty($mutual_contacts_names)) {
                            $mutual_list = implode(", ", $mutual_contacts_names);
                            $notif_title = "✅ Emergency Contacts Notified!";
                            $notif_msg = "Your trusted contact $mutual_list has received an emergency alert for your SOS ($type). They know where you are. Location: https://maps.google.com/?q=$lat,$lng";
                        } else {
                            $notif_title = "🚨 Emergency Contacts Alerted!";
                            $notif_msg = "Your emergency contacts (" . implode(", ", $alerted_contacts) . ") have been sent an SMS alert regarding your SOS ($type).";
                        }
                    }
                    
                    $safe_title = mysqli_real_escape_string($conn, $notif_title);
                    $safe_msg = mysqli_real_escape_string($conn, $notif_msg);
                    mysqli_query($conn, "INSERT INTO notifications (user_id, title, message, is_read) VALUES ('$user_id', '$safe_title', '$safe_msg', 0)");
                    
                    if ($sender_lang === 'so') {
                        $response['message'] .= "\nEhelkaada (" . implode(", ", $alerted_contacts) . ") wargelin SMS ah ayaa loo diray!";
                    } else {
                        $response['message'] .= "\nYour emergency contacts (" . implode(", ", $alerted_contacts) . ") have been alerted via SMS!";
                    }
                }
            }
        }
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