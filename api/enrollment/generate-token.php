<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);

$input = json_decode(file_get_contents('php://input'), true);
$policyId = (int)($input['policy_id'] ?? 0);

$token = generateToken(ENROLLMENT_TOKEN_LENGTH);
$expiresAt = date('Y-m-d H:i:s', time() + ENROLLMENT_TOKEN_EXPIRY);

$db = getDB();
$stmt = $db->prepare("INSERT INTO enrollment_tokens (token, policy_id, expires_at) VALUES (?, ?, ?)");
$stmt->execute([$token, $policyId ?: null, $expiresAt]);

jsonResponse(['success' => true, 'token' => $token, 'expires_at' => $expiresAt]);
