<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);

$input = json_decode(file_get_contents('php://input'), true);
$name = trim($input['name'] ?? '');
if (!$name) jsonResponse(['success' => false, 'message' => 'Policy name required'], 400);

$db = getDB();
$stmt = $db->prepare("INSERT INTO policies (name, description, kiosk_mode, disable_play_store, disable_camera, disable_bluetooth, disable_usb, disable_factory_reset) VALUES (?,?,?,?,?,?,?,?)");
$stmt->execute([
    $name,
    $input['description'] ?? '',
    (int)($input['kiosk_mode'] ?? 1),
    (int)($input['disable_play_store'] ?? 1),
    (int)($input['disable_camera'] ?? 0),
    (int)($input['disable_bluetooth'] ?? 0),
    (int)($input['disable_usb'] ?? 0),
    (int)($input['disable_factory_reset'] ?? 1),
]);
jsonResponse(['success' => true, 'message' => 'Policy created']);
