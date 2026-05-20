<?php
/**
 * Device List API — used by Analytics live device panel
 * Returns enrolled devices with online status, battery, user info
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$db = getDB();

$stmt = $db->query("
    SELECT
        d.id,
        d.imei,
        d.device_name,
        d.manufacturer,
        d.model,
        d.enrollment_status,
        d.is_online,
        d.battery_level,
        d.ip_address,
        d.last_heartbeat,
        d.os_version,
        u.display_name AS user_name,
        u.id AS user_id
    FROM devices d
    LEFT JOIN users u ON u.device_id = d.id
    WHERE d.enrollment_status = 'enrolled'
    ORDER BY d.is_online DESC, d.last_heartbeat DESC
");

$devices = $stmt ? $stmt->fetchAll() : [];

// Mark devices offline if no heartbeat in last OFFLINE_THRESHOLD seconds
$now = time();
foreach ($devices as &$device) {
    if ($device['last_heartbeat']) {
        $lastBeat = strtotime($device['last_heartbeat']);
        if (($now - $lastBeat) > OFFLINE_THRESHOLD) {
            $device['is_online'] = 0;
        }
    }
}
unset($device);

jsonResponse(['success' => true, 'devices' => $devices]);
