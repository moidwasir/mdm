<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);

$input = json_decode(file_get_contents('php://input'), true);
$id = isset($input['id']) ? (int)$input['id'] : 0;
$name = trim($input['name'] ?? '');

if (!$id) jsonResponse(['success' => false, 'message' => 'Policy ID required'], 400);
if (!$name) jsonResponse(['success' => false, 'message' => 'Policy name required'], 400);

$db = getDB();
// Check if policy exists
$check = $db->prepare("SELECT id FROM policies WHERE id = ?");
$check->execute([$id]);
if (!$check->fetch()) {
    jsonResponse(['success' => false, 'message' => 'Policy not found'], 404);
}

$stmt = $db->prepare("UPDATE policies SET name = ?, description = ?, kiosk_mode = ?, disable_play_store = ?, disable_camera = ?, disable_bluetooth = ?, disable_usb = ?, disable_factory_reset = ? WHERE id = ?");
$stmt->execute([
    $name,
    $input['description'] ?? '',
    (int)($input['kiosk_mode'] ?? 0),
    (int)($input['disable_play_store'] ?? 0),
    (int)($input['disable_camera'] ?? 0),
    (int)($input['disable_bluetooth'] ?? 0),
    (int)($input['disable_usb'] ?? 0),
    (int)($input['disable_factory_reset'] ?? 0),
    $id
]);

jsonResponse(['success' => true, 'message' => 'Policy updated']);
