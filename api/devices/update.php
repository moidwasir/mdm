<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$id = (int)($input['id'] ?? 0);

if (!$id) {
    jsonResponse(['success' => false, 'message' => 'Device ID required'], 400);
}

$db = getDB();
$fields = [];
$params = [];

$allowedFields = ['device_name', 'manufacturer', 'model', 'enrollment_status', 'policy_id', 'notes'];
foreach ($allowedFields as $field) {
    if (isset($input[$field])) {
        $fields[] = "$field = ?";
        $params[] = $input[$field];
    }
}

if (empty($fields)) {
    jsonResponse(['success' => false, 'message' => 'No fields to update'], 400);
}

$params[] = $id;
$sql = "UPDATE devices SET " . implode(', ', $fields) . " WHERE id = ?";
$db->prepare($sql)->execute($params);

jsonResponse(['success' => true, 'message' => 'Device updated']);
