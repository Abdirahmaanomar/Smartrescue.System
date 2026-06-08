<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['photo'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit();
}

$file = $_FILES['photo'];
$allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
$max_size = 5 * 1024 * 1024; // 5 MB

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'error', 'message' => 'Upload error: ' . $file['error']]);
    exit();
}

if (!in_array($file['type'], $allowed_types)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid file type. Only JPG, PNG, and WebP are allowed.']);
    exit();
}

if ($file['size'] > $max_size) {
    echo json_encode(['status' => 'error', 'message' => 'File is too large. Maximum size is 5MB.']);
    exit();
}

$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
if (empty($ext)) {
    $ext = explode('/', $file['type'])[1];
}

$new_filename = uniqid('img_', true) . '.' . $ext;
$upload_dir = '../../uploads/';

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$destination = $upload_dir . $new_filename;

if (move_uploaded_file($file['tmp_name'], $destination)) {
    // Return relative path from root document
    $public_path = 'uploads/' . $new_filename;
    echo json_encode([
        'status' => 'success', 
        'message' => 'File uploaded successfully',
        'file_path' => $public_path
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to save uploaded file']);
}
