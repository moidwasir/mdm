<?php
$pageTitle = 'Policies';
require_once __DIR__ . '/../includes/auth-check.php';
$db = getDB();
$policies = $db->query("SELECT p.*, (SELECT COUNT(*) FROM devices WHERE policy_id = p.id) as device_count FROM policies p ORDER BY p.is_default DESC, p.name ASC")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header page-header-actions">
    <div><h1><i class="fas fa-sliders"></i> Policies</h1><p>Device restriction configurations</p></div>
    <button class="btn btn-primary" onclick="document.getElementById('add-policy-modal').classList.add('active')"><i class="fas fa-plus"></i> New Policy</button>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:20px;">
    <?php foreach ($policies as $p): ?>
    <div class="card" style="position:relative;">
        <?php if ($p['is_default']): ?><span class="badge badge-info" style="position:absolute;top:16px;right:16px;">Default</span><?php endif; ?>
        <h3 style="font-size:1.1rem;font-weight:700;margin-bottom:4px;"><?= sanitize($p['name']) ?></h3>
        <p style="color:var(--text-muted);font-size:0.8rem;margin-bottom:16px;"><?= sanitize($p['description'] ?? 'No description') ?></p>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:16px;">
            <div style="display:flex;align-items:center;gap:6px;font-size:0.8rem;"><i class="fas <?= $p['kiosk_mode'] ? 'fa-check-circle' : 'fa-circle-xmark' ?>" style="color:<?= $p['kiosk_mode'] ? 'var(--success)' : 'var(--text-muted)' ?>"></i> Kiosk Mode</div>
            <div style="display:flex;align-items:center;gap:6px;font-size:0.8rem;"><i class="fas <?= $p['disable_play_store'] ? 'fa-check-circle' : 'fa-circle-xmark' ?>" style="color:<?= $p['disable_play_store'] ? 'var(--success)' : 'var(--text-muted)' ?>"></i> Block Store</div>
            <div style="display:flex;align-items:center;gap:6px;font-size:0.8rem;"><i class="fas <?= $p['disable_camera'] ? 'fa-check-circle' : 'fa-circle-xmark' ?>" style="color:<?= $p['disable_camera'] ? 'var(--success)' : 'var(--text-muted)' ?>"></i> Block Camera</div>
            <div style="display:flex;align-items:center;gap:6px;font-size:0.8rem;"><i class="fas <?= $p['disable_usb'] ? 'fa-check-circle' : 'fa-circle-xmark' ?>" style="color:<?= $p['disable_usb'] ? 'var(--success)' : 'var(--text-muted)' ?>"></i> Block USB</div>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center;padding-top:12px;border-top:1px solid var(--border-color);">
            <span style="font-size:0.8rem;color:var(--text-muted);"><i class="fas fa-mobile-screen"></i> <?= $p['device_count'] ?> devices</span>
            <button class="btn btn-danger btn-sm btn-icon" onclick="deletePolicy(<?= $p['id'] ?>)" title="Delete"><i class="fas fa-trash"></i></button>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Add Policy Modal -->
<div class="modal-overlay" id="add-policy-modal">
    <div class="modal">
        <div class="modal-header"><h3 class="modal-title">Create Policy</h3><button class="modal-close" onclick="this.closest('.modal-overlay').classList.remove('active')">&times;</button></div>
        <form id="add-policy-form">
            <div class="form-group"><label class="form-label">Policy Name *</label><input type="text" name="name" class="form-control" required placeholder="e.g. Full Lockdown"></div>
            <div class="form-group"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2" placeholder="Policy description"></textarea></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px;">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:0.85rem;"><input type="checkbox" name="kiosk_mode" value="1" checked> Kiosk Mode</label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:0.85rem;"><input type="checkbox" name="disable_play_store" value="1" checked> Disable Play Store</label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:0.85rem;"><input type="checkbox" name="disable_camera" value="1"> Disable Camera</label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:0.85rem;"><input type="checkbox" name="disable_bluetooth" value="1"> Disable Bluetooth</label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:0.85rem;"><input type="checkbox" name="disable_usb" value="1"> Disable USB</label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:0.85rem;"><input type="checkbox" name="disable_factory_reset" value="1" checked> Block Factory Reset</label>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Create</button>
                <button type="button" class="btn btn-secondary" onclick="this.closest('.modal-overlay').classList.remove('active')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('add-policy-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(this));
    // Convert checkboxes
    ['kiosk_mode','disable_play_store','disable_camera','disable_bluetooth','disable_usb','disable_factory_reset'].forEach(k => {
        data[k] = data[k] ? 1 : 0;
    });
    fetch('<?= APP_URL ?>/api/policies/create.php', {
        method:'POST', headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
        body: JSON.stringify(data)
    }).then(r=>r.json()).then(d=>{
        if(d.success){showToast('Policy created!','success');setTimeout(()=>location.reload(),1000);}
        else showToast(d.message,'error');
    });
});
function deletePolicy(id){if(confirm('Delete this policy?'))fetch('<?= APP_URL ?>/api/policies/delete.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({id})}).then(r=>r.json()).then(d=>{if(d.success)location.reload();else alert(d.message);});}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
