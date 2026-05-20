<?php
$pageTitle = 'Device Logs';
require_once __DIR__ . '/../includes/auth-check.php';
$db = getDB();

$page = max(1, (int)($_GET['page'] ?? 1));
$query = "SELECT dl.*, d.imei, d.device_name FROM device_logs dl JOIN devices d ON dl.device_id = d.id ORDER BY dl.created_at DESC";
$result = paginate($db, $query, [], $page);

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header"><h1><i class="fas fa-clock-rotate-left"></i> Device Logs</h1><p>Activity history across all devices</p></div>

<div class="card">
    <?php if (empty($result['items'])): ?>
        <div class="empty-state"><i class="fas fa-clock-rotate-left"></i><h3>No logs yet</h3><p>Device activity will appear here</p></div>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead><tr><th>Device</th><th>Event</th><th>Details</th><th>Time</th></tr></thead>
                <tbody>
                <?php foreach ($result['items'] as $log): ?>
                    <tr>
                        <td><div class="device-info"><div class="device-icon"><i class="fas fa-mobile-screen"></i></div><div><div class="device-name"><?= sanitize($log['device_name'] ?: 'Unknown') ?></div><div class="device-imei"><?= sanitize($log['imei']) ?></div></div></div></td>
                        <td><span class="badge badge-info"><?= sanitize($log['event_type']) ?></span></td>
                        <td style="color:var(--text-secondary);font-size:0.85rem;max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= sanitize($log['details'] ?? '—') ?></td>
                        <td style="color:var(--text-muted);font-size:0.8rem;"><?= timeAgo($log['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= paginationHTML($result['page'], $result['total_pages'], 'logs.php') ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
