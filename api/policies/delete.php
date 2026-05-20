<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);

$input = json_decode(file_get_contents('php://input'), true);
$id = (int)($input['id'] ?? 0);
if (!$id) jsonResponse(['success' => false, 'message' => 'Policy ID required'], 400);

$db = getDB();
$check = $db->prepare("SELECT is_default FROM policies WHERE id = ?");
$check->execute([$id]);
$policy = $check->fetch();
if ($policy && $policy['is_default']) jsonResponse(['success' => false, 'message' => 'Cannot delete default policy'], 400);

$db->prepare("UPDATE devices SET policy_id = NULL WHERE policy_id = ?")->execute([$id]);
$db->prepare("DELETE FROM policies WHERE id = ?")->execute([$id]);
jsonResponse(['success' => true, 'message' => 'Policy deleted']);
