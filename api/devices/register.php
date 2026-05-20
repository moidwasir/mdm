<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$imei = trim($input['imei'] ?? '');

if (!$imei) {
    jsonResponse(['success' => false, 'message' => 'IMEI is required'], 400);
}

if (!validateIMEI($imei)) {
    jsonResponse(['success' => false, 'message' => 'Invalid IMEI number. Must be 15 digits with valid checksum.'], 400);
}

$db = getDB();

// Check duplicate
$stmt = $db->prepare("SELECT id FROM devices WHERE imei = ?");
$stmt->execute([$imei]);
if ($stmt->fetch()) {
    jsonResponse(['success' => false, 'message' => 'Device with this IMEI already exists'], 409);
}

$stmt = $db->prepare("INSERT INTO devices (imei, device_name, manufacturer, model, policy_id, notes, enrollment_status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
$stmt->execute([
    $imei,
    sanitize($input['device_name'] ?? ''),
    sanitize($input['manufacturer'] ?? ''),
    sanitize($input['model'] ?? ''),
    $input['policy_id'] ? (int)$input['policy_id'] : null,
    sanitize($input['notes'] ?? '')
]);

$deviceId = $db->lastInsertId();
logAction('enrollment', 'Device registered via admin panel', $deviceId);

jsonResponse(['success' => true, 'message' => 'Device registered successfully', 'device_id' => $deviceId]);
