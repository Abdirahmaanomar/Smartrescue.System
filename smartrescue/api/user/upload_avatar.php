<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['avatar'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit();
}

$file = $_FILES['avatar'];
$allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$max_size = 5 * 1024 * 1024; // 5 MB

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'error', 'message' => 'Upload error: ' . $file['error']]);
    exit();
}

if (!in_array($file['type'], $allowed_types)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid file type. Only JPG, PNG, WebP and GIF are allowed.']);
    exit();
}

if ($file['size'] > $max_size) {
    echo json_encode(['status' => 'error', 'message' => 'File too large. Maximum size is 5MB.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$upload_dir = '../../uploads/avatars/';

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Delete old avatar file if it exists
$old_res = mysqli_query($conn, "SELECT profile_image FROM users WHERE id='$user_id' LIMIT 1");
$old_row = mysqli_fetch_assoc($old_res);
if (!empty($old_row['profile_image'])) {
    $old_path = '../../' . $old_row['profile_image'];
    if (file_exists($old_path)) {
        @unlink($old_path);
    }
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (empty($ext)) {
    $ext = explode('/', $file['type'])[1];
}

$new_filename = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
$destination  = $upload_dir . $new_filename;
$public_path  = 'uploads/avatars/' . $new_filename;

if (move_uploaded_file($file['tmp_name'], $destination)) {
    $safe_path = mysqli_real_escape_string($conn, $public_path);
    mysqli_query($conn, "UPDATE users SET profile_image='$safe_path' WHERE id='$user_id'");
    $_SESSION['profile_image'] = $public_path;
    echo json_encode([
        'status'     => 'success',
        'message'    => 'Avatar uploaded successfully',
        'file_path'  => $public_path
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to save file. Check folder permissions.']);
}
