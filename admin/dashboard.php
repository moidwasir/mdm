<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/auth-check.php';

$db = getDB();

// Stats
$totalDevices = $db->query("SELECT COUNT(*) FROM devices")->fetchColumn();
$enrolledDevices = $db->query("SELECT COUNT(*) FROM devices WHERE enrollment_status = 'enrolled'")->fetchColumn();
$onlineDevices = $db->query("SELECT COUNT(*) FROM devices WHERE is_online = 1")->fetchColumn();
$totalUsers = $db->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();
$pendingDevices = $db->query("SELECT COUNT(*) FROM devices WHERE enrollment_status = 'pending'")->fetchColumn();
$totalMessages = $db->query("SELECT COUNT(*) FROM messages")->fetchColumn();

// Recent devices
$recentDevices = $db->query("SELECT d.*, u.display_name as user_name FROM devices d LEFT JOIN users u ON d.assigned_user_id = u.id ORDER BY d.created_at DESC LIMIT 5")->fetchAll();

// Recent logs
$recentLogs = $db->query("SELECT dl.*, d.imei, d.device_name FROM device_logs dl JOIN devices d ON dl.device_id = d.id ORDER BY dl.created_at DESC LIMIT 8")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-chart-pie"></i> Dashboard</h1>
    <p>Overview of your device fleet and system status</p>
</div>

<div class="stats-grid">
    <div class="stat-card purple">
        <div class="stat-icon"><i class="fas fa-mobile-screen"></i></div>
        <div class="stat-value"><?= $totalDevices ?></div>
        <div class="stat-label">Total Devices</div>
        <div class="stat-change up"><i class="fas fa-arrow-up"></i> <?= $enrolledDevices ?> enrolled</div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon"><i class="fas fa-wifi"></i></div>
        <div class="stat-value"><?= $onlineDevices ?></div>
        <div class="stat-label">Online Now</div>
        <div class="stat-change"><?= $totalDevices > 0 ? round(($onlineDevices / $totalDevices) * 100) : 0 ?>% of fleet</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon"><i class="fas fa-users"></i></div>
        <div class="stat-value"><?= $totalUsers ?></div>
        <div class="stat-label">Active Users</div>
    </div>
    <div class="stat-card orange">
        <div class="stat-icon"><i class="fas fa-clock"></i></div>
        <div class="stat-value"><?= $pendingDevices ?></div>
        <div class="stat-label">Pending Enrollment</div>
    </div>
</div>

<div class="grid-2">
    <!-- Recent Devices -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-mobile-screen"></i> Recent Devices</h3>
            <a href="devices.php" class="btn btn-secondary btn-sm">View All</a>
        </div>
        <?php if (empty($recentDevices)): ?>
            <div class="empty-state">
                <i class="fas fa-mobile-screen"></i>
                <h3>No devices yet</h3>
                <p>Add your first device to get started</p>
                <a href="add-device.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add Device</a>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead><tr><th>Device</th><th>Status</th><th>Added</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentDevices as $device): ?>
                        <tr>
                            <td>
                                <div class="device-info">
                                    <div class="device-icon"><i class="fas fa-mobile-screen"></i></div>
                                    <div>
                                        <div class="device-name"><?= sanitize($device['device_name'] ?: $device['model'] ?: 'Unknown') ?></div>
                                        <div class="device-imei"><?= sanitize($device['imei']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?= getStatusBadge($device['enrollment_status']) ?></td>
                            <td style="color:var(--text-muted);font-size:0.8rem"><?= timeAgo($device['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Recent Activity -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-clock-rotate-left"></i> Recent Activity</h3>
            <a href="logs.php" class="btn btn-secondary btn-sm">View All</a>
        </div>
        <?php if (empty($recentLogs)): ?>
            <div class="empty-state">
                <i class="fas fa-clock-rotate-left"></i>
                <h3>No activity yet</h3>
                <p>Device events will appear here</p>
            </div>
        <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:12px;">
                <?php foreach ($recentLogs as $log): ?>
                    <div style="display:flex;align-items:center;gap:12px;padding:10px;border-radius:var(--radius-sm);background:var(--bg-input);">
                        <div style="width:8px;height:8px;border-radius:50%;background:var(--accent);flex-shrink:0;"></div>
                        <div style="flex:1;">
                            <div style="font-size:0.85rem;font-weight:500;"><?= sanitize($log['event_type']) ?></div>
                            <div style="font-size:0.75rem;color:var(--text-muted);"><?= sanitize($log['imei']) ?> · <?= timeAgo($log['created_at']) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
