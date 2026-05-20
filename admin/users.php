<?php
$pageTitle = 'Users';
require_once __DIR__ . '/../includes/auth-check.php';
$db = getDB();

$page = max(1, (int)($_GET['page'] ?? 1));
$query = "SELECT u.*, d.imei, d.device_name, d.model FROM users u LEFT JOIN devices d ON u.device_id = d.id ORDER BY u.created_at DESC";
$result = paginate($db, $query, [], $page);
$devices = $db->query("SELECT id, imei, device_name, model FROM devices WHERE assigned_user_id IS NULL ORDER BY created_at DESC")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header page-header-actions">
    <div><h1><i class="fas fa-users"></i> Users</h1><p><?= $result['total'] ?> chat users</p></div>
    <button class="btn btn-primary" onclick="document.getElementById('add-user-modal').classList.add('active')"><i class="fas fa-plus"></i> Add User</button>
</div>

<div class="card">
    <?php if (empty($result['items'])): ?>
        <div class="empty-state"><i class="fas fa-users"></i><h3>No users yet</h3><p>Create chat users and assign them to devices</p></div>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead><tr><th>User</th><th>Phone</th><th>Device</th><th>Status</th><th>Last Seen</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($result['items'] as $user): ?>
                    <tr>
                        <td>
                            <div class="device-info">
                                <div class="device-icon" style="background:rgba(34,197,94,0.1);color:var(--success);border-radius:50%;"><i class="fas fa-user"></i></div>
                                <div><div class="device-name"><?= sanitize($user['display_name']) ?></div><div class="device-imei">@<?= sanitize($user['username']) ?></div></div>
                            </div>
                        </td>
                        <td style="color:var(--text-secondary)"><?= sanitize($user['phone'] ?? '—') ?></td>
                        <td style="font-size:0.85rem;color:var(--text-secondary);"><?= $user['imei'] ? sanitize($user['device_name'] ?: $user['imei']) : '—' ?></td>
                        <td><?= $user['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Disabled</span>' ?></td>
                        <td style="color:var(--text-muted);font-size:0.8rem;"><?= $user['last_seen'] ? timeAgo($user['last_seen']) : 'Never' ?></td>
                        <td>
                            <button class="btn btn-danger btn-sm btn-icon" onclick="deleteUser(<?= $user['id'] ?>)" title="Delete"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= paginationHTML($result['page'], $result['total_pages'], 'users.php') ?>
    <?php endif; ?>
</div>

<!-- Add User Modal -->
<div class="modal-overlay" id="add-user-modal">
    <div class="modal">
        <div class="modal-header"><h3 class="modal-title">Add New User</h3><button class="modal-close" onclick="this.closest('.modal-overlay').classList.remove('active')">&times;</button></div>
        <form id="add-user-form">
            <div class="form-group"><label class="form-label">Username *</label><input type="text" name="username" class="form-control" required placeholder="e.g. john.doe"></div>
            <div class="form-group"><label class="form-label">Display Name *</label><input type="text" name="display_name" class="form-control" required placeholder="e.g. John Doe"></div>
            <div class="form-group"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" placeholder="+1234567890"></div>
            <div class="form-group">
                <label class="form-label">Assign Device</label>
                <select name="device_id" class="form-control">
                    <option value="">— No device —</option>
                    <?php foreach ($devices as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= sanitize($d['device_name'] ?: $d['model'] ?: $d['imei']) ?> (<?= sanitize($d['imei']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Create User</button>
                <button type="button" class="btn btn-secondary" onclick="this.closest('.modal-overlay').classList.remove('active')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('add-user-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(this));
    fetch('<?= APP_URL ?>/api/users/create.php', {
        method: 'POST', headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
        body: JSON.stringify(data)
    }).then(r => r.json()).then(d => {
        if (d.success) { showToast('User created!', 'success'); setTimeout(() => location.reload(), 1000); }
        else showToast(d.message, 'error');
    });
});
function deleteUser(id) { if (confirm('Delete this user?')) fetch('<?= APP_URL ?>/api/users/delete.php', { method:'POST', headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'}, body:JSON.stringify({id}) }).then(r=>r.json()).then(d=>{if(d.success)location.reload();else alert(d.message);}); }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
