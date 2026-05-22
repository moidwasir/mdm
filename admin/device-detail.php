<?php
$pageTitle = 'Device Detail';
require_once __DIR__ . '/../includes/auth-check.php';
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { redirect('devices.php', 'Device not found', 'error'); }

$stmt = $db->prepare("SELECT d.*, u.display_name as user_name, u.username, p.name as policy_name FROM devices d LEFT JOIN users u ON d.assigned_user_id = u.id LEFT JOIN policies p ON d.policy_id = p.id WHERE d.id = ?");
$stmt->execute([$id]);
$device = $stmt->fetch();
if (!$device) { redirect('devices.php', 'Device not found', 'error'); }

$logs = $db->prepare("SELECT * FROM device_logs WHERE device_id = ? ORDER BY created_at DESC LIMIT 20");
$logs->execute([$id]);
$deviceLogs = $logs->fetchAll();

$commands = $db->prepare("SELECT dc.*, a.full_name as admin_name FROM device_commands dc LEFT JOIN admins a ON dc.issued_by = a.id WHERE dc.device_id = ? ORDER BY dc.created_at DESC LIMIT 10");
$commands->execute([$id]);
$deviceCommands = $commands->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header page-header-actions">
    <div>
        <h1><i class="fas fa-mobile-screen"></i> <?= sanitize($device['device_name'] ?: $device['model'] ?: 'Device #' . $device['id']) ?></h1>
        <p>IMEI: <span style="font-family:monospace;color:var(--accent-light)"><?= sanitize($device['imei']) ?></span></p>
    </div>
    <div style="display:flex;gap:8px;">
        <button class="btn btn-secondary btn-sm" onclick="sendCommand(<?= $id ?>, 'lock')"><i class="fas fa-lock"></i> Lock</button>
        <button class="btn btn-secondary btn-sm" onclick="sendCommand(<?= $id ?>, 'unlock')"><i class="fas fa-unlock"></i> Unlock</button>
        <button class="btn btn-secondary btn-sm" onclick="sendCommand(<?= $id ?>, 'ring')"><i class="fas fa-bell"></i> Ring</button>
        <button class="btn btn-danger btn-sm" onclick="sendCommand(<?= $id ?>, 'wipe')"><i class="fas fa-eraser"></i> Wipe</button>
    </div>
</div>

<!-- Device Info Cards -->
<div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr));">
    <div class="stat-card purple"><div class="stat-icon"><i class="fas fa-signal"></i></div><div class="stat-value"><?= $device['is_online'] ? 'Online' : 'Offline' ?></div><div class="stat-label">Connection</div></div>
    <div class="stat-card green"><div class="stat-icon"><i class="fas fa-battery-half"></i></div><div class="stat-value"><?= $device['battery_level'] !== null ? $device['battery_level'] . '%' : '—' ?></div><div class="stat-label">Battery</div></div>
    <div class="stat-card blue"><div class="stat-icon"><i class="fas fa-hard-drive"></i></div><div class="stat-value"><?= $device['storage_free_mb'] ? formatBytes($device['storage_free_mb'] * 1024 * 1024) : '—' ?></div><div class="stat-label">Free Storage</div></div>
    <div class="stat-card orange"><div class="stat-icon"><i class="fas fa-clock"></i></div><div class="stat-value" style="font-size:1rem;"><?= $device['last_heartbeat'] ? timeAgo($device['last_heartbeat']) : 'Never' ?></div><div class="stat-label">Last Heartbeat</div></div>
</div>

<div class="grid-2">
    <!-- Device Details -->
    <div class="card">
        <div class="card-header"><h3 class="card-title">Device Information</h3></div>
        <div style="display:flex;flex-direction:column;gap:12px;">
            <?php
            $fields = [
                'IMEI' => $device['imei'], 'Status' => $device['enrollment_status'],
                'Lock State' => $device['is_locked'] ? 'Locked 🔒' : 'Unlocked 🔓',
                'Manufacturer' => $device['manufacturer'], 'Model' => $device['model'],
                'OS Version' => $device['os_version'], 'Policy' => $device['policy_name'] ?? 'None',
                'Assigned User' => $device['user_name'] ?? 'Unassigned', 'IP Address' => $device['ip_address'],
                'Network Type' => $device['network_type'] ?: '—', 'WiFi' => $device['wifi_ssid'],
                'Enrolled' => $device['enrolled_at'] ? date('M j, Y H:i', strtotime($device['enrolled_at'])) : '—',
            ];
            foreach ($fields as $label => $val): ?>
                <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border-color);">
                    <span style="color:var(--text-muted);font-size:0.85rem;"><?= $label ?></span>
                    <span style="font-weight:500;font-size:0.85rem;"><?= sanitize($val ?: '—') ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Activity Log -->
    <div class="card">
        <div class="card-header"><h3 class="card-title">Activity Log</h3></div>
        <?php if (empty($deviceLogs)): ?>
            <div class="empty-state" style="padding:30px;"><i class="fas fa-clock-rotate-left"></i><h3>No activity</h3></div>
        <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:8px;max-height:400px;overflow-y:auto;">
                <?php foreach ($deviceLogs as $log): ?>
                    <div style="display:flex;align-items:flex-start;gap:10px;padding:8px;border-radius:var(--radius-sm);background:var(--bg-input);font-size:0.8rem;">
                        <div style="width:6px;height:6px;border-radius:50%;background:var(--accent);margin-top:6px;flex-shrink:0;"></div>
                        <div><div style="font-weight:500;"><?= sanitize($log['event_type']) ?></div><div style="color:var(--text-muted);font-size:0.75rem;"><?= $log['details'] ? sanitize($log['details']) . ' · ' : '' ?><?= timeAgo($log['created_at']) ?></div></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Command History -->
<div class="card" style="margin-top: 24px;">
    <div class="card-header"><h3 class="card-title">Command History</h3></div>
    <?php if (empty($deviceCommands)): ?>
        <div class="empty-state" style="padding:30px;"><i class="fas fa-terminal"></i><h3>No commands issued</h3></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table" style="width:100%;border-collapse:collapse;margin-top:10px;">
                <thead>
                    <tr style="text-align:left;border-bottom:2px solid var(--border-color);color:var(--text-muted);font-size:0.85rem;">
                        <th style="padding:10px 8px;">Command</th>
                        <th style="padding:10px 8px;">Issued By</th>
                        <th style="padding:10px 8px;">Status</th>
                        <th style="padding:10px 8px;">Issued At</th>
                        <th style="padding:10px 8px;">Executed At</th>
                        <th style="padding:10px 8px;">Details / Feedback</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($deviceCommands as $cmd): ?>
                        <tr style="border-bottom:1px solid var(--border-color);font-size:0.85rem;">
                            <td style="padding:12px 8px;"><strong><?= strtoupper(sanitize($cmd['command_type'])) ?></strong></td>
                            <td style="padding:12px 8px;"><?= sanitize($cmd['admin_name'] ?: 'System') ?></td>
                            <td style="padding:12px 8px;">
                                <?php
                                $statusClass = 'badge-secondary';
                                if ($cmd['status'] === 'pending') $statusClass = 'badge-warning';
                                elseif ($cmd['status'] === 'sent') $statusClass = 'badge-info';
                                elseif ($cmd['status'] === 'executed') $statusClass = 'badge-success';
                                elseif ($cmd['status'] === 'failed') $statusClass = 'badge-danger';
                                ?>
                                <span class="badge <?= $statusClass ?>" style="padding:4px 8px;border-radius:4px;font-size:0.75rem;font-weight:600;display:inline-block;"><?= strtoupper(sanitize($cmd['status'])) ?></span>
                            </td>
                            <td style="padding:12px 8px;color:var(--text-muted);"><?= date('M j, Y H:i:s', strtotime($cmd['created_at'])) ?></td>
                            <td style="padding:12px 8px;color:var(--text-muted);"><?= $cmd['executed_at'] ? date('M j, Y H:i:s', strtotime($cmd['executed_at'])) : '—' ?></td>
                            <td style="padding:12px 8px;">
                                <?php if ($cmd['status'] === 'failed'): ?>
                                    <span style="color:#EF4444;font-weight:500;"><i class="fas fa-circle-exclamation"></i> Failed: <?= sanitize($cmd['error_message']) ?></span>
                                <?php elseif ($cmd['status'] === 'executed'): ?>
                                    <span style="color:#10B981;font-weight:500;"><i class="fas fa-circle-check"></i> Executed successfully</span>
                                <?php elseif ($cmd['status'] === 'sent'): ?>
                                    <span style="color:#3B82F6;font-weight:500;"><i class="fas fa-spinner fa-spin"></i> Delivered to device</span>
                                <?php else: ?>
                                    <span style="color:var(--text-muted);">Awaiting queue...</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
function sendCommand(deviceId, type) {
    const messages = {
        lock: 'Lock this device in Lockdown Mode?',
        unlock: 'Unlock this device from Lockdown Mode?',
        ring: 'Ring this device with a loud alarm?',
        wipe: '⚠️ WIPE this device? This will permanently erase ALL data!'
    };
    if (!confirm(messages[type])) return;
    fetch('<?= APP_URL ?>/api/devices/command.php', {
        method:'POST', headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
        body: JSON.stringify({device_id: deviceId, command_type: type})
    }).then(r=>r.json()).then(d=>{
        if(d.success) {
            showToast('Command sent!','success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(d.message,'error');
        }
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
