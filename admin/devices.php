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
    <div style="display:flex;gap:12px;">
        <button class="btn btn-secondary" id="btn-download-apk" onclick="showDownloadModal()"><i class="fas fa-download"></i> Download APK</button>
        <a href="add-device.php" class="btn btn-primary" id="btn-add-device"><i class="fas fa-plus"></i> Add Device</a>
    </div>
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

<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
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

function showDownloadModal() {
    const modal = document.createElement('div');
    modal.id = 'download-modal';
    modal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);display:flex;align-items:center;justify-content:center;z-index:10000;';

    const container = document.createElement('div');
    container.style.cssText = 'background:var(--card-bg);border-radius:var(--radius-lg);padding:32px;max-width:400px;width:90%;text-align:center;';

    const title = document.createElement('h2');
    title.style.cssText = 'color:var(--text-primary);margin:0 0 16px;font-size:18px;';
    title.textContent = 'Download MDM Agent APK';

    const qrContainer = document.createElement('div');
    qrContainer.style.cssText = 'margin:16px 0;';
    const qrDiv = document.createElement('div');
    qrDiv.style.cssText = 'display:inline-block;padding:16px;background:white;border-radius:var(--radius-sm);';

    new QRCode(qrDiv, {
        text: '<?= APP_URL ?>/apk/mdm-agent.apk',
        width: 180,
        height: 180
    });
    qrContainer.appendChild(qrDiv);

    const linkDiv = document.createElement('div');
    linkDiv.style.cssText = 'margin-top:16px;font-size:13px;color:var(--text-secondary);text-align:left;';
    linkDiv.innerHTML = `
        <span style="display:block;margin-bottom:6px;text-align:center;">Or open this link on your mobile device:</span>
        <div style="display:flex;align-items:center;justify-content:center;gap:8px;background:var(--bg-input);padding:8px 12px;border:1px solid var(--border-color);border-radius:var(--radius-sm);word-break:break-all;">
            <a href="<?= APP_URL ?>/apk/mdm-agent.apk" target="_blank" style="color:var(--accent-light);text-decoration:underline;font-family:monospace;font-size:12px;"><?= APP_URL ?>/apk/mdm-agent.apk</a>
            <button type="button" class="btn btn-secondary btn-sm" style="padding:4px 8px;font-size:11px;min-height:auto;flex-shrink:0;" onclick="navigator.clipboard.writeText('<?= APP_URL ?>/apk/mdm-agent.apk').then(() => { if(typeof showToast === 'function') { showToast('Link copied!', 'success'); } else { alert('Link copied!'); } })">
                <i class="fas fa-copy"></i>
            </button>
        </div>
    `;
    qrContainer.appendChild(linkDiv);

    const link = document.createElement('a');
    link.href = '<?= APP_URL ?>/apk/mdm-agent.apk';
    link.download = 'mdm-agent.apk';
    link.style.cssText = 'display:inline-block;margin-top:16px;padding:12px 24px;background:var(--accent-blue-start);color:white;border-radius:var(--radius-sm);text-decoration:none;font-weight:bold;';
    link.textContent = 'Direct Download';

    const closeBtn = document.createElement('button');
    closeBtn.textContent = 'Close';
    closeBtn.style.cssText = 'margin-top:12px;padding:8px 24px;background:transparent;color:var(--text-secondary);border:1px solid var(--border);border-radius:var(--radius-sm);cursor:pointer;font-size:14px;';
    closeBtn.onclick = () => modal.remove();

    container.appendChild(title);
    container.appendChild(qrContainer);
    container.appendChild(link);
    container.appendChild(closeBtn);
    modal.appendChild(container);
    document.body.appendChild(modal);
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
