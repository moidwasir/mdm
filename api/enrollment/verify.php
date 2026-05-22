<?php
/**
 * Enrollment Verification API
 * Called by the MDM Agent on first boot to complete enrollment
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);

$input = json_decode(file_get_contents('php://input'), true);
$token = trim($input['token'] ?? '');
$imei  = trim($input['imei'] ?? '');

if (!$token || !$imei) jsonResponse(['success' => false, 'message' => 'Token and IMEI required'], 400);
if (!validateIMEI($imei)) jsonResponse(['success' => false, 'message' => 'Invalid IMEI format'], 400);

$db = getDB();

// Validate token
$tStmt = $db->prepare("SELECT * FROM enrollment_tokens WHERE token = ? AND is_used = 0 AND expires_at > NOW()");
$tStmt->execute([$token]);
$enrollToken = $tStmt->fetch();

if (!$enrollToken) jsonResponse(['success' => false, 'message' => 'Invalid or expired enrollment token'], 403);

// Find or create the device record
$dStmt = $db->prepare("SELECT * FROM devices WHERE imei = ?");
$dStmt->execute([$imei]);
$device = $dStmt->fetch();

if (!$device) {
    // Auto-create device if not pre-registered (open enrollment)
    $db->prepare("INSERT INTO devices (imei, enrollment_status, policy_id, enrolled_at) VALUES (?, 'enrolled', ?, NOW())")->execute([$imei, $enrollToken['policy_id']]);
    $deviceId = (int)$db->lastInsertId();
} else {
    $deviceId = (int)$device['id'];
    $db->prepare("UPDATE devices SET enrollment_status = 'enrolled', policy_id = COALESCE(?, policy_id), enrolled_at = NOW() WHERE id = ?")->execute([$enrollToken['policy_id'], $deviceId]);
}

// Update device info from agent report
$db->prepare("UPDATE devices SET manufacturer = COALESCE(?, manufacturer), model = COALESCE(?, model), os_version = COALESCE(?, os_version), ip_address = ?, last_heartbeat = NOW(), is_online = 1 WHERE id = ?")->execute([
    $input['manufacturer'] ?? null,
    $input['model'] ?? null,
    $input['os_version'] ?? null,
    $_SERVER['REMOTE_ADDR'],
    $deviceId,
]);

// Mark token as used
$db->prepare("UPDATE enrollment_tokens SET is_used = 1, used_by_imei = ? WHERE id = ?")->execute([$imei, $enrollToken['id']]);

// Log enrollment
logAction('enrollment', "Device enrolled via QR token. IMEI: $imei", $deviceId);

// Fetch policy for the device
$policy = null;
if ($enrollToken['policy_id']) {
    $p = $db->prepare("SELECT * FROM policies WHERE id = ?");
    $p->execute([$enrollToken['policy_id']]);
    $policy = $p->fetch();
}

jsonResponse([
    'success'   => true,
    'message'   => 'Device enrolled successfully',
    'device_id' => $deviceId,
    'policy'    => formatPolicy($policy),
    'ws_url'    => 'ws://' . $_SERVER['HTTP_HOST'] . ':' . WEBSOCKET_PORT,
    'api_url'   => APP_URL . '/api',
]);
