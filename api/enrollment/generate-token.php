<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$input    = json_decode(file_get_contents('php://input'), true);
$policyId = isset($input['policy_id']) && $input['policy_id'] ? (int)$input['policy_id'] : null;
$label    = trim($input['label'] ?? '');
$expHours = min(168, max(1, (int)($input['expires_hours'] ?? 24)));

$token     = generateToken(ENROLLMENT_TOKEN_LENGTH);
$expiresAt = date('Y-m-d H:i:s', time() + ($expHours * 3600));

$db   = getDB();
$stmt = $db->prepare("INSERT INTO enrollment_tokens (token, qr_data, policy_id, expires_at) VALUES (?, ?, ?, ?)");
$stmt->execute([$token, $label ?: null, $policyId, $expiresAt]);

$enrollmentUrl = APP_URL . '/enroll?token=' . urlencode($token);

jsonResponse([
    'success'        => true,
    'token'          => $token,
    'expires_at'     => $expiresAt,
    'enrollment_url' => $enrollmentUrl,
    'label'          => $label ?: null,
]);
