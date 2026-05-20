<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);

$input = json_decode(file_get_contents('php://input'), true);
$deviceId = (int)($input['device_id'] ?? 0);
$commandType = $input['command_type'] ?? '';

if (!$deviceId || !$commandType) jsonResponse(['success' => false, 'message' => 'Device ID and command type required'], 400);

$validCommands = ['lock', 'unlock', 'wipe', 'restart', 'update_policy', 'install_app', 'uninstall_app', 'ring', 'message'];
if (!in_array($commandType, $validCommands)) jsonResponse(['success' => false, 'message' => 'Invalid command type'], 400);

$db = getDB();
session_start();
$adminId = $_SESSION['admin_id'] ?? null;

$stmt = $db->prepare("INSERT INTO device_commands (device_id, command_type, payload, issued_by) VALUES (?, ?, ?, ?)");
$stmt->execute([$deviceId, $commandType, json_encode($input['payload'] ?? null), $adminId]);

logAction('command_received', "Command: $commandType", $deviceId);
jsonResponse(['success' => true, 'message' => 'Command queued', 'command_id' => $db->lastInsertId()]);
