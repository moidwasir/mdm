<?php
/**
 * Chat Authentication API
 * Called by the Android Chat App to authenticate a user by device IMEI
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-MDM-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);

$input = json_decode(file_get_contents('php://input'), true);
$imei  = trim($input['imei'] ?? '');

if (!$imei) jsonResponse(['success' => false, 'message' => 'IMEI required'], 400);

$db   = getDB();
$stmt = $db->prepare("SELECT d.*, u.id as user_id, u.username, u.display_name, u.avatar FROM devices d LEFT JOIN users u ON d.assigned_user_id = u.id WHERE d.imei = ? AND d.enrollment_status = 'enrolled'");
$stmt->execute([$imei]);
$device = $stmt->fetch();

if (!$device) jsonResponse(['success' => false, 'message' => 'Device not enrolled or not found'], 403);
if (!$device['user_id']) jsonResponse(['success' => false, 'message' => 'No user assigned to this device'], 403);

// Generate a session token for WebSocket auth
$token = bin2hex(random_bytes(32));
$expiry = date('Y-m-d H:i:s', time() + 86400);

// Store token in a temporary table (we reuse enrollment_tokens table with a special marker)
$db->prepare("INSERT INTO enrollment_tokens (token, is_used, expires_at) VALUES (?, 0, ?) ON DUPLICATE KEY UPDATE expires_at = ?")->execute(["chat_{$token}", $expiry, $expiry]);

// Update last seen
$db->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?")->execute([$device['user_id']]);

jsonResponse([
    'success'      => true,
    'token'        => $token,
    'user_id'      => $device['user_id'],
    'username'     => $device['username'],
    'display_name' => $device['display_name'],
    'avatar'       => $device['avatar'],
    'device_id'    => $device['id'],
    'ws_url'       => 'ws://' . $_SERVER['HTTP_HOST'] . ':' . WEBSOCKET_PORT,
]);
