<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

// Accept both GET and POST for heartbeat
$input = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? json_decode(file_get_contents('php://input'), true)
    : $_GET;

$imei = trim($input['imei'] ?? '');
if (!$imei) {
    jsonResponse(['success' => false, 'message' => 'IMEI required'], 400);
}

$db = getDB();
$stmt = $db->prepare("SELECT id, policy_id FROM devices WHERE imei = ? AND enrollment_status = 'enrolled'");
$stmt->execute([$imei]);
$device = $stmt->fetch();

if (!$device) {
    jsonResponse(['success' => false, 'message' => 'Device not found or not enrolled'], 404);
}

// Update heartbeat data
$update = $db->prepare("UPDATE devices SET last_heartbeat = NOW(), is_online = 1, battery_level = ?, ip_address = ?, storage_free_mb = ?, os_version = ?, wifi_ssid = ?, latitude = ?, longitude = ? WHERE id = ?");
$update->execute([
    $input['battery_level'] ?? null,
    $input['ip_address'] ?? $_SERVER['REMOTE_ADDR'],
    $input['storage_free_mb'] ?? null,
    $input['os_version'] ?? null,
    $input['wifi_ssid'] ?? null,
    $input['latitude'] ?? null,
    $input['longitude'] ?? null,
    $device['id']
]);

// Check for pending commands
$cmds = $db->prepare("SELECT id, command_type, payload FROM device_commands WHERE device_id = ? AND status = 'pending' ORDER BY created_at ASC");
$cmds->execute([$device['id']]);
$pendingCommands = $cmds->fetchAll();

// Mark commands as sent
if ($pendingCommands) {
    $ids = array_column($pendingCommands, 'id');
    $db->prepare("UPDATE device_commands SET status = 'sent' WHERE id IN (" . implode(',', $ids) . ")")->execute();
}

// Get current policy
$policy = null;
if ($device['policy_id']) {
    $p = $db->prepare("SELECT * FROM policies WHERE id = ?");
    $p->execute([$device['policy_id']]);
    $policy = $p->fetch();
}

// Get latest app versions for OTA update checks
$appVersionsStmt = $db->query("SELECT app_name, package_name, version_name, version_code, apk_url FROM app_versions");
$appVersions = $appVersionsStmt ? $appVersionsStmt->fetchAll() : [];

jsonResponse([
    'success'      => true,
    'commands'     => $pendingCommands,
    'policy'       => $policy,
    'interval'     => HEARTBEAT_INTERVAL,
    'app_versions' => $appVersions,     // OTA version catalog
]);
