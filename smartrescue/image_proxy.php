<?php
// Image proxy — serves uploaded avatars with correct CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$path = $_GET['path'] ?? '';

// Security: only allow uploads/avatars path, no directory traversal
$path = str_replace(['..', '\\', "\0"], '', $path);
$path = ltrim($path, '/');

if (!preg_match('/^uploads\/avatars\/[a-zA-Z0-9_\-\.]+\.(jpg|jpeg|png|gif|webp)$/i', $path)) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit();
}

$fullPath = __DIR__ . '/' . $path;

if (!file_exists($fullPath)) {
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
    exit();
}

$ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
$mimeTypes = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
];

header('Content-Type: ' . ($mimeTypes[$ext] ?? 'application/octet-stream'));
header('Cache-Control: public, max-age=3600');
readfile($fullPath);
