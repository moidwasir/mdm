<?php
$pageTitle = 'Devices';
require_once __DIR__ . '/../includes/auth-check.php';
$db = getDB();

$page = max(1, (int)($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';

$where = "WHERE 1=1";
$params = [];
if ($search) { $where .= " AND (d.imei LIKE ? OR d.device_name LIKE ? OR d.model LIKE ?)"; $s = "%$search%"; $params = [$s, $s, $s]; }
if ($statusFilter) { $where .= " AND d.enrollment_status = ?"; $params[] = $statusFilter; }

$query = "SELECT d.*, u.display_name as user_name, p.name as policy_name FROM devices d LEFT JOIN users u ON d.assigned_user_id = u.id LEFT JOIN policies p ON d.policy_id = p.id $where ORDER BY d.created_at DESC";
$result = paginate($db, $query, $params, $page);

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header page-header-actions">
    <div>
        <h1><i class="fas fa-mobile-screen"></i> Devices</h1>
        <p><?= $result['total'] ?> total devices</p>
    </div>
    <a href="add-device.php" class="btn btn-primary" id="btn-add-device"><i class="fas fa-plus"></i> Add Device</a>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom:20px;padding:16px;">
    <form method="GET" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
        <input type="text" name="search" class="form-control" placeholder="Search IMEI, name, model..." value="<?= sanitize($search) ?>" style="max-width:280px;">
        <select name="status" class="form-control" style="max-width:180px;">
            <option value="">All Status</option>
            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="enrolled" <?= $statusFilter === 'enrolled' ? 'selected' : '' ?>>Enrolled</option>
            <option value="unenrolled" <?= $statusFilter === 'unenrolled' ? 'selected' : '' ?>>Unenrolled</option>
            <option value="blocked" <?= $statusFilter === 'blocked' ? 'selected' : '' ?>>Blocked</option>
        </select>
        <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-filter"></i> Filter</button>
        <?php if ($search || $statusFilter): ?>
            <a href="devices.php" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> Clear</a>
        <?php endif; ?>
    </form>
</div>

<div class="card">
    <?php if (empty($result['items'])): ?>
        <div class="empty-state">
            <i class="fas fa-mobile-screen"></i>
            <h3>No devices found</h3>
            <p>Add your first device using the IMEI barcode scanner</p>
            <a href="add-device.php" class="btn btn-primary"><i class="fas fa-barcode"></i> Scan Barcode</a>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr><th>Device</th><th>Status</th><th>Online</th><th>Policy</th><th>User</th><th>Last Seen</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($result['items'] as $device): ?>
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
                        <td><?= $device['is_online'] ? getStatusBadge('online') : getStatusBadge('offline') ?></td>
                        <td style="color:var(--text-secondary);font-size:0.85rem;"><?= sanitize($device['policy_name'] ?? 'None') ?></td>
                        <td style="color:var(--text-secondary);font-size:0.85rem;"><?= sanitize($device['user_name'] ?? '—') ?></td>
                        <td style="color:var(--text-muted);font-size:0.8rem;"><?= $device['last_heartbeat'] ? timeAgo($device['last_heartbeat']) : '—' ?></td>
                        <td>
                            <div style="display:flex;gap:6px;">
                                <a href="device-detail.php?id=<?= $device['id'] ?>" class="btn btn-secondary btn-sm btn-icon" title="View"><i class="fas fa-eye"></i></a>
                                <button class="btn btn-danger btn-sm btn-icon" title="Block" onclick="blockDevice(<?= $device['id'] ?>)"><i class="fas fa-ban"></i></button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= paginationHTML($result['page'], $result['total_pages'], 'devices.php') ?>
    <?php endif; ?>
</div>

<script>
function blockDevice(id) {
    if (!confirm('Are you sure you want to block this device?')) return;
    fetch('<?= APP_URL ?>/api/devices/update.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
        body: JSON.stringify({id: id, enrollment_status: 'blocked'})
    }).then(r => r.json()).then(d => {
        if (d.success) location.reload();
        else alert(d.message);
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
