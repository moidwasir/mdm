<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$imei = trim($input['imei'] ?? '');

if (!$imei) {
    jsonResponse(['success' => false, 'message' => 'IMEI is required'], 400);
}

if (!validateIMEI($imei)) {
    jsonResponse(['success' => false, 'message' => 'Invalid IMEI format'], 400);
}

$db = getDB();

// Find device record
$stmt = $db->prepare("SELECT * FROM devices WHERE imei = ?");
$stmt->execute([$imei]);
$device = $stmt->fetch();

if (!$device) {
    jsonResponse([
        'success' => true,
        'registered' => false,
        'message' => 'Device IMEI is not registered on the portal. Please add this device to the admin portal first.'
    ]);
}

if ($device['enrollment_status'] === 'enrolled') {
    jsonResponse([
        'success' => true,
        'registered' => true,
        'enrolled' => true,
        'message' => 'Device is already enrolled.'
    ]);
}

// Device exists and is pending/unenrolled, get or generate enrollment token.
// Find if there is an active, unused token for this device or IMEI
$tokenStmt = $db->prepare("SELECT * FROM enrollment_tokens WHERE (device_id = ? OR used_by_imei = ?) AND is_used = 0 AND expires_at > NOW() LIMIT 1");
$tokenStmt->execute([$device['id'], $imei]);
$tokenRow = $tokenStmt->fetch();

if ($tokenRow) {
    $token = $tokenRow['token'];
} else {
    // Generate new token
    $token = bin2hex(random_bytes(16));
    $expires = date('Y-m-d H:i:s', strtotime('+2 hours'));

    // Insert token
    $insertStmt = $db->prepare("INSERT INTO enrollment_tokens (token, policy_id, device_id, expires_at) VALUES (?, ?, ?, ?)");
    $insertStmt->execute([$token, $device['policy_id'], $device['id'], $expires]);
}

jsonResponse([
    'success' => true,
    'registered' => true,
    'enrolled' => false,
    'token' => $token,
    'message' => 'Device is registered and ready for enrollment.'
]);
