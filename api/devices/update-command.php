<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$commandId = (int)($input['command_id'] ?? 0);
$status = $input['status'] ?? '';
$errorMessage = $input['error_message'] ?? null;

if (!$commandId || !in_array($status, ['executed', 'failed'])) {
    jsonResponse(['success' => false, 'message' => 'Valid Command ID and status (executed/failed) required'], 400);
}

$db = getDB();

// Check if command exists
$stmt = $db->prepare("SELECT id, device_id, command_type FROM device_commands WHERE id = ?");
$stmt->execute([$commandId]);
$command = $stmt->fetch();

if (!$command) {
    jsonResponse(['success' => false, 'message' => 'Command not found'], 404);
}

// Update command status
$update = $db->prepare("UPDATE device_commands SET status = ?, executed_at = NOW(), error_message = ? WHERE id = ?");
$update->execute([$status, $errorMessage, $commandId]);

// Log to device_logs
$logType = $status === 'executed' ? 'command_executed' : 'error';
$details = "Command: " . $command['command_type'] . " " . ($status === 'executed' ? 'executed successfully' : 'failed: ' . $errorMessage);
$logStmt = $db->prepare("INSERT INTO device_logs (device_id, event_type, details) VALUES (?, ?, ?)");
$logStmt->execute([$command['device_id'], $logType, $details]);

jsonResponse(['success' => true, 'message' => 'Command status updated']);
