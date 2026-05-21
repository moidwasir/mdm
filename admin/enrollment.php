<?php
$pageTitle = 'QR Enrollment';
require_once __DIR__ . '/../includes/auth-check.php';
$db = getDB();
$policies = $db->query("SELECT id, name FROM policies ORDER BY is_default DESC")->fetchAll();
$tokens = $db->query("SELECT et.*, p.name as policy_name FROM enrollment_tokens et LEFT JOIN policies p ON et.policy_id = p.id ORDER BY et.created_at DESC LIMIT 20")->fetchAll();

// Calculate DPC APK Checksum dynamically (SHA-256 base64-url-safe)
$apkPath = APK_DIR . 'mdm-agent.apk';
$apkChecksum = '';
if (file_exists($apkPath)) {
    $sha256 = hash_file('sha256', $apkPath, true);
    $apkChecksum = rtrim(strtr(base64_encode($sha256), '+/', '-_'), '=');
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header page-header-actions">
    <div><h1><i class="fas fa-qrcode"></i> QR Enrollment</h1><p>Generate enrollment tokens for device provisioning</p></div>
    <button class="btn btn-primary" onclick="document.getElementById('gen-token-modal').classList.add('active')"><i class="fas fa-plus"></i> Generate Token</button>
</div>

<div class="card" style="margin-bottom:20px;padding:20px;">
    <div style="display:flex;align-items:center;gap:16px;padding:16px;background:rgba(59,130,246,0.08);border:1px solid rgba(59,130,246,0.15);border-radius:var(--radius-sm);">
        <i class="fas fa-info-circle" style="color:var(--info);font-size:1.5rem;"></i>
        <div>
            <div style="font-weight:600;margin-bottom:4px;">How Device Enrollment Works</div>
            <ol style="color:var(--text-secondary);font-size:0.85rem;margin-left:16px;line-height:2;">
                <li>Generate an enrollment token below</li>
                <li>Factory reset the target Android device</li>
                <li>Tap the screen 6 times to open QR scanner</li>
                <li>Scan the QR code — the device will automatically configure itself</li>
            </ol>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title">Enrollment Tokens</h3></div>
    <?php if (empty($tokens)): ?>
        <div class="empty-state"><i class="fas fa-qrcode"></i><h3>No tokens generated</h3><p>Generate an enrollment token to start enrolling devices</p></div>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead><tr><th>Token</th><th>Policy</th><th>Status</th><th>Expires</th><th>Created</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($tokens as $t): ?>
                    <tr>
                        <td><code style="font-size:0.75rem;color:var(--accent-light);"><?= substr($t['token'], 0, 16) ?>...</code></td>
                        <td style="color:var(--text-secondary);font-size:0.85rem;"><?= sanitize($t['policy_name'] ?? '—') ?></td>
                        <td><?= $t['is_used'] ? '<span class="badge badge-secondary">Used</span>' : (strtotime($t['expires_at']) < time() ? '<span class="badge badge-danger">Expired</span>' : '<span class="badge badge-success">Active</span>') ?></td>
                        <td style="color:var(--text-muted);font-size:0.8rem;"><?= date('M j, H:i', strtotime($t['expires_at'])) ?></td>
                        <td style="color:var(--text-muted);font-size:0.8rem;"><?= timeAgo($t['created_at']) ?></td>
                        <td><button class="btn btn-secondary btn-sm" onclick="showQR('<?= $t['token'] ?>')"><i class="fas fa-qrcode"></i> QR</button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Generate Token Modal -->
<div class="modal-overlay" id="gen-token-modal">
    <div class="modal">
        <div class="modal-header"><h3 class="modal-title">Generate Enrollment Token</h3><button class="modal-close" onclick="this.closest('.modal-overlay').classList.remove('active')">&times;</button></div>
        <form id="gen-token-form">
            <div class="form-group">
                <label class="form-label">Apply Policy</label>
                <select name="policy_id" class="form-control">
                    <?php foreach ($policies as $p): ?><option value="<?= $p['id'] ?>"><?= sanitize($p['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-key"></i> Generate</button>
                <button type="button" class="btn btn-secondary" onclick="this.closest('.modal-overlay').classList.remove('active')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- QR Display Modal -->
<div class="modal-overlay" id="qr-modal">
    <div class="modal" style="text-align:center;">
        <div class="modal-header">
            <h3 class="modal-title">Android Enterprise Provisioning QR</h3>
            <button class="modal-close" onclick="this.closest('.modal-overlay').classList.remove('active')">&times;</button>
        </div>
        <div id="qr-display" style="padding:20px;background:#fff;border-radius:var(--radius-sm);display:inline-block;margin:16px auto;"></div>
        <div style="margin-top:8px;">
            <p style="color:var(--text-muted);font-size:0.8rem;">Scan on a <strong>factory-reset</strong> Android device — tap blank screen 6 times to open QR scanner</p>
            <p style="font-size:0.75rem;color:var(--text-secondary);margin-top:4px;">Token: <code id="qr-token-display" style="color:var(--accent-light);"></code></p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
document.getElementById('gen-token-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(this));
    fetch('<?= APP_URL ?>/api/enrollment/generate-token.php', {
        method:'POST', headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
        body: JSON.stringify(data)
    }).then(r=>r.json()).then(d=>{
        if(d.success){showToast('Token generated!','success');setTimeout(()=>location.reload(),1000);}
        else showToast(d.message,'error');
    });
});
function showQR(token) {
    const modal = document.getElementById('qr-modal');
    const display = document.getElementById('qr-display');
    display.innerHTML = '';

    // Full Android Enterprise DPC provisioning payload
    // When scanned on a factory-reset device (tapped 6 times), Android will:
    // 1. Download the MDM Agent APK from the server
    // 2. Install it as Device Policy Controller (DPC)
    // 3. Pass the enrollment token to the DPC automatically
    const dpcPayload = JSON.stringify({
        "android.app.extra.PROVISIONING_DEVICE_ADMIN_COMPONENT_NAME":
            "com.mdm.agent/com.mdm.agent.DeviceAdminReceiver",
        "android.app.extra.PROVISIONING_DEVICE_ADMIN_PACKAGE_DOWNLOAD_LOCATION":
            `<?= APP_URL ?>/apk/mdm-agent.apk`,
        "android.app.extra.PROVISIONING_DEVICE_ADMIN_PACKAGE_CHECKSUM": "<?= $apkChecksum ?>",
        "android.app.extra.PROVISIONING_LEAVE_ALL_SYSTEM_APPS_ENABLED": false,
        "android.app.extra.PROVISIONING_ADMIN_EXTRAS_BUNDLE": {
            "enrollment_token": token,
            "server_url": "<?= APP_URL ?>"
        }
    });

    new QRCode(display, {
        text: dpcPayload,
        width: 280,
        height: 280,
        colorDark: '#000000',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.M
    });

    modal.classList.add('active');

    // Show raw token for manual entry
    document.getElementById('qr-token-display').textContent = token.substring(0, 16) + '...';
}
</script>

<?php
// Add token display element to QR modal - update modal HTML
?>


<?php include __DIR__ . '/../includes/footer.php'; ?>
