<?php
/**
 * Media Upload API
 * Handles image/file uploads from the chat app
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-User-Id');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);

$userId = (int)($_SERVER['HTTP_X_USER_ID'] ?? 0);
if (!$userId) jsonResponse(['success' => false, 'message' => 'User ID required'], 401);

if (empty($_FILES['file'])) jsonResponse(['success' => false, 'message' => 'No file uploaded'], 400);

$file     = $_FILES['file'];
$maxSize  = MAX_UPLOAD_SIZE;

if ($file['size'] > $maxSize) jsonResponse(['success' => false, 'message' => 'File too large (max 50MB)'], 400);
if ($file['error'] !== UPLOAD_ERR_OK) jsonResponse(['success' => false, 'message' => 'Upload error: ' . $file['error']], 500);

$mimeType = mime_content_type($file['tmp_name']);
$allowed  = array_merge(ALLOWED_IMAGE_TYPES, ALLOWED_FILE_TYPES);
if (!in_array($mimeType, $allowed)) jsonResponse(['success' => false, 'message' => 'File type not allowed'], 400);

// Create upload folder (date-based)
$folder    = UPLOAD_DIR . date('Y/m/');
if (!is_dir($folder)) mkdir($folder, 0755, true);

// Unique filename
$ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = generateToken(16) . '.' . strtolower($ext);
$dest     = $folder . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    jsonResponse(['success' => false, 'message' => 'Failed to save file'], 500);
}

$relPath  = 'assets/uploads/' . date('Y/m/') . $filename;
$fullUrl  = APP_URL . '/' . $relPath;

jsonResponse([
    'success'   => true,
    'url'       => $fullUrl,
    'filename'  => $file['name'],
    'size'      => $file['size'],
    'mime_type' => $mimeType,
]);
