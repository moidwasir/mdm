<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);

$input = json_decode(file_get_contents('php://input'), true);
$id = (int)($input['id'] ?? 0);
if (!$id) jsonResponse(['success' => false, 'message' => 'User ID required'], 400);

$db = getDB();
$db->prepare("UPDATE devices SET assigned_user_id = NULL WHERE assigned_user_id = ?")->execute([$id]);
$db->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
jsonResponse(['success' => true, 'message' => 'User deleted']);
