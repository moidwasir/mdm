<?php
$pageTitle = 'Add Device';
$pageScript = 'barcode-scanner.js';
require_once __DIR__ . '/../includes/auth-check.php';
$db = getDB();
$policies = $db->query("SELECT id, name FROM policies ORDER BY is_default DESC, name ASC")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-barcode"></i> Add Device</h1>
    <p>Register a new device by scanning its IMEI barcode or entering manually</p>
</div>

<div class="grid-2">
    <!-- Barcode Scanner -->
    <div class="card" id="scanner-card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-camera"></i> IMEI Barcode Scanner</h3>
            <button class="btn btn-primary btn-sm" id="btn-start-scan"><i class="fas fa-play"></i> Start Scanner</button>
        </div>
        <div id="scanner-container" style="border-radius:var(--radius-sm);overflow:hidden;background:#000;min-height:300px;display:flex;align-items:center;justify-content:center;">
            <div style="text-align:center;color:var(--text-muted);padding:40px;">
                <i class="fas fa-barcode" style="font-size:3rem;margin-bottom:16px;display:block;"></i>
                <p>Click "Start Scanner" to scan IMEI barcode</p>
                <p style="font-size:0.75rem;margin-top:8px;">Requires camera permission</p>
            </div>
        </div>
        <div id="scan-result" style="margin-top:16px;display:none;">
            <div style="display:flex;align-items:center;gap:10px;padding:14px;background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.2);border-radius:var(--radius-sm);">
                <i class="fas fa-check-circle" style="color:var(--success);font-size:1.2rem;"></i>
                <div>
                    <div style="font-weight:600;font-size:0.9rem;">IMEI Detected</div>
                    <div id="scanned-imei" style="font-family:monospace;color:var(--accent-light);font-size:1.1rem;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Manual Entry Form -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-keyboard"></i> Device Details</h3>
        </div>
        <form id="add-device-form" method="POST">
            <div class="form-group">
                <label class="form-label">IMEI Number *</label>
                <input type="text" name="imei" id="imei-input" class="form-control" placeholder="Enter 15-digit IMEI" maxlength="15" pattern="[0-9]{15}" required>
                <small style="color:var(--text-muted);font-size:0.75rem;">15 digits, found on device box or dial *#06#</small>
            </div>
            <div class="form-group">
                <label class="form-label">Device Name</label>
                <input type="text" name="device_name" class="form-control" placeholder="e.g. Office Phone 1">
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Manufacturer</label>
                    <input type="text" name="manufacturer" class="form-control" placeholder="e.g. Samsung">
                </div>
                <div class="form-group">
                    <label class="form-label">Model</label>
                    <input type="text" name="model" class="form-control" placeholder="e.g. Galaxy A54">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Policy</label>
                <select name="policy_id" class="form-control">
                    <?php foreach ($policies as $policy): ?>
                        <option value="<?= $policy['id'] ?>"><?= sanitize($policy['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="3" placeholder="Optional notes about this device"></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary" id="btn-submit-device"><i class="fas fa-plus"></i> Register Device</button>
                <a href="devices.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
document.getElementById('add-device-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const data = Object.fromEntries(formData);

    fetch('<?= APP_URL ?>/api/devices/register.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            showToast('Device registered successfully!', 'success');
            // Show modal with QR code
            showSuccessModal();
        } else {
            showToast(d.message || 'Failed to register device', 'error');
        }
    })
    .catch(() => showToast('Network error', 'error'));
});

function showSuccessModal() {
    const modal = document.createElement('div');
    modal.id = 'success-modal';
    modal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);display:flex;align-items:center;justify-content:center;z-index:10000;';

    const container = document.createElement('div');
    container.style.cssText = 'background:var(--card-bg);border-radius:var(--radius-lg);padding:32px;max-width:500px;width:90%;max-height:90vh;overflow-y:auto;';

    const title = document.createElement('h2');
    title.style.cssText = 'color:var(--text-primary);margin:0 0 8px;font-size:20px;text-align:center;';
    title.textContent = 'Device Registered Successfully!';

    const subtitle = document.createElement('p');
    subtitle.style.cssText = 'color:var(--text-secondary);margin:0 0 24px;font-size:14px;text-align:center;';
    subtitle.textContent = 'Scan the QR code below to download and install the MDM Agent.';

    const qrContainer = document.createElement('div');
    qrContainer.style.cssText = 'text-align:center;margin-bottom:24px;';
    const qrDiv = document.createElement('div');
    qrDiv.style.cssText = 'display:inline-block;padding:16px;background:white;border-radius:var(--radius-sm);';

    new QRCode(qrDiv, {
        text: '<?= APP_URL ?>/apk/mdm-agent.apk',
        width: 200,
        height: 200
    });
    qrContainer.appendChild(qrDiv);

    const linkDiv = document.createElement('div');
    linkDiv.style.cssText = 'margin-top:16px;font-size:13px;color:var(--text-secondary);';
    linkDiv.innerHTML = `
        <span style="display:block;margin-bottom:6px;">Or open this link in your mobile browser:</span>
        <div style="display:flex;align-items:center;justify-content:center;gap:8px;background:var(--bg-input);padding:8px 12px;border:1px solid var(--border-color);border-radius:var(--radius-sm);word-break:break-all;">
            <a href="<?= APP_URL ?>/apk/mdm-agent.apk" target="_blank" style="color:var(--accent-light);text-decoration:underline;font-family:monospace;font-size:12px;"><?= APP_URL ?>/apk/mdm-agent.apk</a>
            <button type="button" class="btn btn-secondary btn-sm" style="padding:4px 8px;font-size:11px;min-height:auto;flex-shrink:0;" onclick="navigator.clipboard.writeText('<?= APP_URL ?>/apk/mdm-agent.apk').then(() => showToast('Link copied!', 'success'))">
                <i class="fas fa-copy"></i>
            </button>
        </div>
    `;
    qrContainer.appendChild(linkDiv);

    const steps = document.createElement('div');
    steps.style.cssText = 'background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.2);border-radius:var(--radius-sm);padding:16px;margin-bottom:16px;';
    steps.innerHTML = `
        <h4 style="margin:0 0 12px;color:var(--success);font-size:14px;">Follow these steps:</h4>
        <ol style="margin:0;padding-left:20px;color:var(--text-secondary);font-size:13px;line-height:1.6;">
            <li style="margin-bottom:8px;">Scan the QR code or use the link below to download and install the MDM Agent</li>
            <li style="margin-bottom:8px;">Connect device via USB and run:<br><code style="background:rgba(0,0,0,0.05);padding:2px 6px;border-radius:4px;font-size:12px;">adb shell dpm set-device-owner com.mdm.agent/com.mdm.agent.DeviceAdminReceiver</code></li>
            <li style="margin-bottom:0;">Open the app on the phone and tap "ACTIVATE MDM SECURE MODE"</li>
        </ol>
    `;

    const closeBtn = document.createElement('button');
    closeBtn.textContent = 'Close';
    closeBtn.style.cssText = 'width:100%;padding:12px;background:var(--accent-blue-start);color:white;border:none;border-radius:var(--radius-sm);font-size:14px;font-weight:bold;cursor:pointer;';
    closeBtn.onclick = () => { modal.remove(); window.location.href = 'devices.php'; };

    container.appendChild(title);
    container.appendChild(subtitle);
    container.appendChild(qrContainer);
    container.appendChild(steps);
    container.appendChild(closeBtn);
    modal.appendChild(container);
    document.body.appendChild(modal);
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
