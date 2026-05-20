<?php
/**
 * FCM Token Registration API
 * Called by the Chat App after login to register / update push token
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

$userId   = (int)($_SERVER['HTTP_X_USER_ID'] ?? 0);
$input    = json_decode(file_get_contents('php://input'), true);
$fcmToken = trim($input['fcm_token'] ?? '');

if (!$userId || !$fcmToken) jsonResponse(['success' => false, 'message' => 'user_id and fcm_token required'], 400);

$db = getDB();
$db->prepare("UPDATE users SET fcm_token = ?, last_seen = NOW() WHERE id = ?")->execute([$fcmToken, $userId]);

jsonResponse(['success' => true, 'message' => 'FCM token registered']);
