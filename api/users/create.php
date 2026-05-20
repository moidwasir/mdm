<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);

$input = json_decode(file_get_contents('php://input'), true);
$username = trim($input['username'] ?? '');
$displayName = trim($input['display_name'] ?? '');

if (!$username || !$displayName) jsonResponse(['success' => false, 'message' => 'Username and display name required'], 400);

$db = getDB();
$check = $db->prepare("SELECT id FROM users WHERE username = ?");
$check->execute([$username]);
if ($check->fetch()) jsonResponse(['success' => false, 'message' => 'Username already exists'], 409);

$stmt = $db->prepare("INSERT INTO users (username, display_name, phone, device_id) VALUES (?, ?, ?, ?)");
$stmt->execute([$username, $displayName, $input['phone'] ?? null, $input['device_id'] ?: null]);

if ($input['device_id']) {
    $db->prepare("UPDATE devices SET assigned_user_id = ? WHERE id = ?")->execute([$db->lastInsertId(), $input['device_id']]);
}

jsonResponse(['success' => true, 'message' => 'User created']);
