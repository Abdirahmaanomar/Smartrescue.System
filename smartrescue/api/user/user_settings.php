<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

$user_id = $_POST['user_id'] ?? $_SESSION['user_id'] ?? null;

// Debug log to capture incoming request
file_put_contents('debug_user_settings.txt', date('Y-m-d H:i:s') . "\nPOST: " . print_r($_POST, true) . "\nSESSION: " . print_r($_SESSION, true) . "\n\n", FILE_APPEND);

if ($user_id === null || $user_id === '') {
    // TEMPORARY BYPASS: If old Flutter app doesn't send user_id, find them by old password
    if (isset($_POST['action']) && $_POST['action'] === 'change_password' && !empty($_POST['old_password'])) {
        $old_pass_check = $_POST['old_password'];
        $all_users = mysqli_query($conn, "SELECT id, password FROM users");
        while ($row = mysqli_fetch_assoc($all_users)) {
            if (password_verify($old_pass_check, $row['password'])) {
                $user_id = $row['id'];
                break;
            }
        }
    }
    
    // If still no user_id, block them
    if ($user_id === null || $user_id === '') {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized - App Needs Restart']);
        exit();
    }
}
$action = $_POST['action'] ?? '';

if ($action === 'update_profile') {
    file_put_contents('debug_upload.txt', date('Y-m-d H:i:s') . "\nFILES: " . print_r($_FILES, true) . "\nPOST: " . print_r($_POST, true) . "\n", FILE_APPEND);
    $fullname = clean_input($_POST['fullname'], $conn);
    $phone = clean_input($_POST['phone'], $conn);
    $email_val = clean_input($_POST['email'], $conn);
    $email_sql = !empty($email_val) ? "'$email_val'" : 'NULL';
    
    $birth_date_part = "";
    if (isset($_POST['birth_date'])) {
        $birth_date = !empty($_POST['birth_date']) ? "'".clean_input($_POST['birth_date'], $conn)."'" : 'NULL';
        $birth_date_part = ", birth_date = $birth_date";
    }
    
    $gender_part = "";
    if (isset($_POST['gender'])) {
        $gender = clean_input($_POST['gender'], $conn);
        $gender_part = ", gender = '$gender'";
    }


    if (empty($fullname) || empty($phone)) {
        echo json_encode(['status' => 'error', 'message' => 'Name and Phone are required']);
        exit();
    }

    // Hubi haddii qof kale uu leeyahay phone-ka ama email-ka
    $check_dup = mysqli_query($conn, "SELECT id, phone, email FROM users WHERE (phone = '$phone' OR (email IS NOT NULL AND email != '' AND email = $email_sql)) AND id != '$user_id'");
    if (mysqli_num_rows($check_dup) > 0) {
        $existing = mysqli_fetch_assoc($check_dup);
        if ($existing['phone'] === $phone) {
            echo json_encode(['status' => 'error', 'message' => 'This phone number is already registered by another user!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'This email is already registered by another user!']);
        }
        exit();
    }

    $avatar_query = "";
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../uploads/avatars/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        
        // Validate file type
        $allowed = ['image/jpeg','image/jpg','image/png','image/gif','image/webp'];
        $ftype = mime_content_type($_FILES['avatar']['tmp_name']);
        if (!in_array($ftype, $allowed)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid file type. Use JPG, PNG or GIF.']);
            exit();
        }
        
        // Delete old avatar if it exists
        try {
            $old_q = mysqli_query($conn, "SELECT profile_image FROM users WHERE id='$user_id'");
            if ($old_q) {
                $old = mysqli_fetch_assoc($old_q);
                if (!empty($old['profile_image']) && file_exists('../../' . $old['profile_image'])) {
                    unlink('../../' . $old['profile_image']);
                }
            }
        } catch (Exception $e) { }
        
        $ext       = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $file_name = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
        $target_file = $upload_dir . $file_name;
        $db_path     = 'uploads/avatars/' . $file_name;
        
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $target_file)) {
            $avatar_query = ", profile_image='$db_path'";
            $_SESSION['profile_image'] = $db_path;
        }
    }

    $query = "UPDATE users SET fullname = '$fullname', phone = '$phone', email = $email_sql $birth_date_part $gender_part $avatar_query WHERE id = '$user_id'";
    if (mysqli_query($conn, $query)) {
        $_SESSION['fullname'] = $fullname; // Update session
        
        $res = mysqli_query($conn, "SELECT profile_image FROM users WHERE id = '$user_id'");
        $user_row = mysqli_fetch_assoc($res);
        $current_image = $user_row['profile_image'] ?? '';

        echo json_encode(['status' => 'success', 'message' => 'Profile updated successfully', 'profile_image' => $current_image]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . mysqli_error($conn)]);
    }
} 

elseif ($action === 'change_password') {
    $old_pass = $_POST['old_password'];
    $new_pass = $_POST['new_password'];

    $user_query = "SELECT password FROM users WHERE id = '$user_id'";
    $res = mysqli_query($conn, $user_query);
    $user = mysqli_fetch_assoc($res);

    if (password_verify($old_pass, $user['password'])) {
        $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
        $update = "UPDATE users SET password = '$hashed' WHERE id = '$user_id'";
        if (mysqli_query($conn, $update)) {
            echo json_encode(['status' => 'success', 'message' => 'Password changed successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database error']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Incorrect current password']);
    }
}

elseif ($action === 'toggle_preference') {
    $pref = clean_input($_POST['preference'], $conn);
    $raw_val = $_POST['value'] ?? '';
    
    // Parse boolean strings from Flutter or JS safely
    if (strtolower($raw_val) === 'true' || $raw_val === '1') {
        $value = 1;
    } elseif (strtolower($raw_val) === 'false' || $raw_val === '0') {
        $value = 0;
    } else {
        $value = clean_input($raw_val, $conn);
    }
    
    if ($pref === 'dark_mode') {
        $query = "UPDATE users SET dark_mode = '$value' WHERE id = '$user_id'";
    } else if (in_array($pref, ['language', 'gps_enabled', 'share_live_location', 'location_history', 'vibration_enabled', 'notifications_enabled', 'gps_access', 'live_sos_location', 'time_format_24h', 'sound_alerts', 'emergency_updates', 'location_sharing', 'auto_gps_tracking', 'session_timeout', 'is_volunteer'])) {
        $query = "UPDATE users SET $pref = '$value' WHERE id = '$user_id'";
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid preference']);
        exit();
    }

    if (mysqli_query($conn, $query)) {
        echo json_encode(['status' => 'success', 'value' => $value]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database error']);
    }
}

elseif ($action === 'update_safety_info') {
    $medical = clean_input($_POST['medical_info'], $conn);
    $contacts = clean_input($_POST['emergency_contacts'], $conn);
    $is_blood_donor = isset($_POST['is_blood_donor']) ? intval($_POST['is_blood_donor']) : 0;
    $blood_group = clean_input($_POST['blood_group'] ?? '', $conn);

    $query = "UPDATE users SET medical_info = '$medical', emergency_contacts = '$contacts' WHERE id = '$user_id'";
    if (mysqli_query($conn, $query)) {
        
        // Handle Blood Donor Registry Sync
        $user_q = mysqli_query($conn, "SELECT fullname, phone, current_lat, current_lng FROM users WHERE id = '$user_id'");
        if ($user_q && mysqli_num_rows($user_q) > 0) {
            $u = mysqli_fetch_assoc($user_q);
            $phone = $u['phone'];
            
            if ($is_blood_donor == 1 && !empty($blood_group) && $blood_group !== '— Select —') {
                $name = $u['fullname'];
                $lat = $u['current_lat'] ? $u['current_lat'] : 'NULL';
                $lng = $u['current_lng'] ? $u['current_lng'] : 'NULL';
                
                $check = mysqli_query($conn, "SELECT id FROM blood_donors WHERE phone = '$phone'");
                if (mysqli_num_rows($check) > 0) {
                    mysqli_query($conn, "UPDATE blood_donors SET name='$name', blood_type='$blood_group', is_available=1, lat=$lat, lng=$lng WHERE phone='$phone'");
                } else {
                    mysqli_query($conn, "INSERT INTO blood_donors (name, blood_type, phone, lat, lng, is_available) VALUES ('$name', '$blood_group', '$phone', $lat, $lng, 1)");
                }
            } else {
                // Remove or mark unavailable if opted out
                mysqli_query($conn, "UPDATE blood_donors SET is_available=0 WHERE phone='$phone'");
            }
        }

        echo json_encode(['status' => 'success', 'message' => 'Safety information updated']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database error']);
    }
}
elseif ($action === 'delete_account') {
    $password = $_POST['password'] ?? '';
    
    $user_query = "SELECT password FROM users WHERE id = '$user_id'";
    $res = mysqli_query($conn, $user_query);
    $user = mysqli_fetch_assoc($res);
    
    if (password_verify($password, $user['password'])) {
        // Delete related data first if needed, though CASCADE usually handles this if set.
        mysqli_query($conn, "DELETE FROM rescue_requests WHERE user_id = '$user_id'");
        $del = "DELETE FROM users WHERE id = '$user_id'";
        if (mysqli_query($conn, $del)) {
            session_destroy();
            echo json_encode(['status' => 'success', 'message' => 'Account deleted']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database error']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Incorrect password']);
    }
}
?>
