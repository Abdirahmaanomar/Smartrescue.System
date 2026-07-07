<?php
// Only run through CLI
if (php_sapi_name() !== 'cli') {
    header("HTTP/1.1 403 Forbidden");
    die("Access denied.");
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Parse command line arguments with support for '--' option
$args = $argv;
if (isset($args[1]) && $args[1] === '--') {
    array_shift($args); // remove script path
    array_shift($args); // remove '--'
} else {
    array_shift($args); // remove script path
}

// Map arguments
$inserted_id        = isset($args[0]) ? intval($args[0]) : 0;
$user_id            = isset($args[1]) ? intval($args[1]) : 0;
$lat                = isset($args[2]) ? floatval($args[2]) : 0.0;
$lng                = isset($args[3]) ? floatval($args[3]) : 0.0;
$type               = isset($args[4]) ? clean_input($args[4], $conn) : '';
$fullname           = isset($args[5]) ? clean_input($args[5], $conn) : 'User';
$sender_phone       = isset($args[6]) ? clean_input($args[6], $conn) : '';
$sender_lang        = isset($args[7]) ? clean_input($args[7], $conn) : 'en';
$neighborhood       = isset($args[8]) ? clean_input($args[8], $conn) : '';
$emergency_contacts_encoded = isset($args[9]) ? $args[9] : '';

$emergency_contacts = !empty($emergency_contacts_encoded) ? base64_decode($emergency_contacts_encoded) : '';

if (!$inserted_id || !$user_id) {
    log_activity($conn, $user_id, 'SOS Background Fail', 'Missing inserted_id or user_id in background worker args', 'error');
    exit();
}

// A. Background Reverse Geocoding (if neighborhood was empty)
if (empty($neighborhood)) {
    $geocoded_neighborhood = php_reverse_geocode($lat, $lng);
    if (!empty($geocoded_neighborhood)) {
        $safe_neighborhood = clean_input($geocoded_neighborhood, $conn);
        mysqli_query($conn, "UPDATE rescue_requests SET neighborhood = '$safe_neighborhood' WHERE id = '$inserted_id'");
        $neighborhood = $geocoded_neighborhood; // update variable for notifications
    }
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
}

// B. Send SMS and In-app notifications in background
if (!empty($emergency_contacts)) {
    $lines = explode("\n", $emergency_contacts);
    $mutual_contacts_names = [];
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        $parts = explode(":", $line);
        $c_name = isset($parts[0]) ? trim($parts[0]) : 'Contact';
        $c_phone = isset($parts[1]) ? trim($parts[1]) : '';
        $c_relation = isset($parts[2]) ? trim($parts[2]) : 'Family';
        
        if (!empty($c_phone)) {
            // Send SMS
            $alert_msg = "SmartRescue SOS Alert: " . $fullname . " has sent an emergency SOS request (" . $type . "). Please check on them immediately. Location: https://maps.google.com/?q=" . $lat . "," . $lng;
            if (!empty($neighborhood)) {
                $alert_msg .= " (Xaafadda: $neighborhood)";
            }
            send_hormuud_sms($conn, $c_phone, $alert_msg, $user_id);
            
            // In-app Notification for emergency contact
            $clean_contact_phone = preg_replace('/[^0-9]/', '', $c_phone);
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
                
                if ($contact_user_id != $user_id) {
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
                    
                    if ($is_mutual) {
                        $mutual_contacts_names[] = $c_name;
                    }
                    
                    $loc_link = "https://maps.google.com/?q=$lat,$lng";
                    if (!empty($neighborhood)) {
                        $loc_link .= " ($neighborhood)";
                    }
                    if ($contact_lang === 'so') {
                        if ($is_mutual) {
                            $in_app_title = "🚨 Gurmadka Ehelka!";
                            $in_app_msg = "Ehelkaaga aad aamintay $fullname ayaa dalbaday SOS degdeg ah ($type)! Fadlan caawi hadda. Goobta: $loc_link";
                        } else {
                            $in_app_title = "🚨 Ogeysiis SOS Ehelka!";
                            $in_app_msg = "$fullname ($c_relation) waxay ku jiraan xaalad degdeg ah ($type)! Fadlan caawi ama hubi hadda. Goobta: $loc_link";
                        }
                    } else {
                        if ($is_mutual) {
                            $in_app_title = "🚨 Trusted Contact SOS Alert!";
                            $in_app_msg = "Your trusted contact $fullname has triggered an emergency SOS ($type)! Please help now. Location: $loc_link";
                        } else {
                            $in_app_title = "🚨 Emergency SOS Alert!";
                            $in_app_msg = "$fullname ($c_relation) is in an emergency situation ($type)! Please help or check on them now. Location: $loc_link";
                        }
                    }
                    
                    $safe_in_app_title = mysqli_real_escape_string($conn, $in_app_title);
                    $safe_in_app_msg = mysqli_real_escape_string($conn, $in_app_msg);
                    mysqli_query($conn, "INSERT INTO notifications (user_id, title, message, is_read) VALUES ('$contact_user_id', '$safe_in_app_title', '$safe_in_app_msg', 0)");
                }
            }
        }
    }
}

// Sender's notification (always runs regardless of emergency contacts)
$loc_link = "https://maps.google.com/?q=$lat,$lng";
if (!empty($neighborhood)) {
    $loc_link .= " ($neighborhood)";
}

if (!empty($emergency_contacts)) {
    if ($sender_lang === 'so') {
        if (!empty($mutual_contacts_names)) {
            $mutual_list = implode(", ", $mutual_contacts_names);
            $notif_title = "✅ Gurmadka Ehelkaaga La Ogaysiiyay!";
            $notif_msg = "Ehelkaada aaminsan $mutual_list ayaa ogeysiis degdeg ah ka helay SOS-kaaga ($type). Waxay kuu ogaayaan halka aad joogto. Goobta: $loc_link";
        } else {
            $notif_title = "🚨 Eheladaada Gurmadka waa la Wargeliyay!";
            $notif_msg = "Eheladaada gurmadka ee (" . implode(", ", $alerted_contacts) . ") waxaa loo diray SMS wargelin ah oo ku saabsan SOS-kaaga ($type).";
        }
    } else {
        if (!empty($mutual_contacts_names)) {
            $mutual_list = implode(", ", $mutual_contacts_names);
            $notif_title = "✅ Emergency Contacts Notified!";
            $notif_msg = "Your trusted contact $mutual_list has received an emergency alert for your SOS ($type). They know where you are. Location: $loc_link";
        } else {
            $notif_title = "🚨 Emergency Contacts Alerted!";
            $notif_msg = "Your emergency contacts (" . implode(", ", $alerted_contacts) . ") have been sent an SMS alert regarding your SOS ($type).";
        }
    }
} else {
    // No emergency contacts configured
    if ($sender_lang === 'so') {
        $notif_title = "🚨 Codsiga SOS-ka waa la Baahiyay!";
        $notif_msg = "Dalabkaaga gurmadka degdegga ah ee ($type) waa la diray. Kooxaha gurmadka ayaa la ogeysiiyay. Goobta: $loc_link";
    } else {
        $notif_title = "🚨 SOS Signal Broadcasted!";
        $notif_msg = "Your emergency SOS request ($type) has been broadcasted. Emergency response teams have been notified. Location: $loc_link";
    }
}

$safe_title = mysqli_real_escape_string($conn, $notif_title);
$safe_msg = mysqli_real_escape_string($conn, $notif_msg);
mysqli_query($conn, "INSERT INTO notifications (user_id, title, message, is_read) VALUES ('$user_id', '$safe_title', '$safe_msg', 0)");
?>
