<?php
$pageTitle = 'Enrollment';
require_once __DIR__ . '/../includes/auth-check.php';
$db = getDB();

// Load policies
$policies = $db->query("SELECT id, name, description FROM policies ORDER BY is_default DESC, name ASC")->fetchAll();

// Load recent tokens (last 20)
$tokens = $db->query(
    "SELECT et.*, p.name AS policy_name,
            CASE
                WHEN et.is_used = 1 THEN 'used'
                WHEN et.expires_at < NOW() THEN 'expired'
                ELSE 'active'
            END AS token_status
     FROM enrollment_tokens et
     LEFT JOIN policies p ON et.policy_id = p.id
     ORDER BY et.created_at DESC LIMIT 30"
)->fetchAll();

// APK SHA-256 signature checksum for DPC provisioning QR
define('DPC_CERT_SHA256', 'c0f4f2091ea3206aa206171d0a608c4e7e895601f08324adf3b037713bce1497');

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header page-header-actions">
    <div>
        <h1><i class="fas fa-qrcode"></i> Enrollment</h1>
        <p>Generate QR codes and links for wireless, zero-touch device setup</p>
    </div>
</div>

<div class="grid-2" style="gap:24px; align-items:start;">

    <!-- Left column: QR Generator -->
    <div style="display:flex; flex-direction:column; gap:20px;">

        <!-- Tab selector for enrollment type -->
        <div class="card" style="padding:0; overflow:hidden;">
            <div style="display:flex; border-bottom:1px solid var(--border-color);">
                <button id="tab-regular" onclick="switchTab('regular')"
                    style="flex:1; padding:16px; border:none; background:var(--accent-color); color:white; font-size:14px; font-weight:600; cursor:pointer; border-radius:0; display:flex; align-items:center; justify-content:center; gap:8px;">
                    <i class="fas fa-qrcode"></i> Enrollment QR
                </button>
                <button id="tab-dpc" onclick="switchTab('dpc')"
                    style="flex:1; padding:16px; border:none; background:transparent; color:var(--text-muted); font-size:14px; font-weight:600; cursor:pointer; border-radius:0; display:flex; align-items:center; justify-content:center; gap:8px;">
                    <i class="fas fa-shield-halved"></i> DPC Provisioning QR
                </button>
            </div>

            <!-- Enrollment QR Panel -->
            <div id="panel-regular" class="card-body" style="padding:28px;">
                <p style="color:var(--text-muted); font-size:13px; margin-bottom:20px; line-height:1.6;">
                    <strong style="color:var(--text-primary);">Regular enrollment</strong> — For existing phones. User scans QR with their camera, opens the link, downloads and installs the MDM app, which auto-configures instantly.
                </p>
                <div style="margin-bottom:16px;">
                    <label style="font-size:13px; font-weight:600; color:var(--text-muted); display:block; margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Policy</label>
                    <select id="policy-select" style="width:100%; padding:12px 14px; background:var(--bg-tertiary); border:1px solid var(--border-color); border-radius:10px; color:var(--text-primary); font-size:14px; font-family:inherit; cursor:pointer;">
                        <option value="">— Auto (Default Policy) —</option>
                        <?php foreach ($policies as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= sanitize($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="margin-bottom:20px;">
                    <label style="font-size:13px; font-weight:600; color:var(--text-muted); display:block; margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Label (optional)</label>
                    <input id="token-label" type="text" placeholder="e.g. Batch #1 — Sales Team"
                           style="width:100%; padding:12px 14px; background:var(--bg-tertiary); border:1px solid var(--border-color); border-radius:10px; color:var(--text-primary); font-size:14px; font-family:inherit;">
                </div>
                <button onclick="generateQR()" id="btn-generate"
                        style="width:100%; padding:14px; background:var(--accent-color); border:none; border-radius:10px; color:white; font-size:15px; font-weight:700; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; transition:all 0.2s;">
                    <i class="fas fa-qrcode"></i> Generate Enrollment QR
                </button>
            </div>

            <!-- DPC Provisioning QR Panel (hidden) -->
            <div id="panel-dpc" class="card-body" style="padding:28px; display:none;">
                <div style="background:rgba(99,102,241,0.08); border:1px solid rgba(99,102,241,0.2); border-radius:10px; padding:14px 16px; margin-bottom:20px;">
                    <p style="font-size:13px; color:#818CF8; font-weight:600; margin-bottom:6px;"><i class="fas fa-info-circle"></i> Enterprise Device Owner Setup</p>
                    <p style="font-size:13px; color:var(--text-muted); line-height:1.6;">
                        This QR is scanned <strong style="color:var(--text-primary);">at the Android setup wizard</strong> (after factory reset, on the first screen). It provisions the device as a fully-managed Device Owner — enabling kiosk lock, factory-reset disable, and all enterprise features. This is the Samsung Knox / Intune method.
                    </p>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="font-size:13px; font-weight:600; color:var(--text-muted); display:block; margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Policy</label>
                    <select id="dpc-policy-select" style="width:100%; padding:12px 14px; background:var(--bg-tertiary); border:1px solid var(--border-color); border-radius:10px; color:var(--text-primary); font-size:14px; font-family:inherit;">
                        <option value="">— Auto (Default Policy) —</option>
                        <?php foreach ($policies as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= sanitize($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button onclick="generateDpcQR()" id="btn-generate-dpc"
                        style="width:100%; padding:14px; background:linear-gradient(135deg,#6366F1,#8B5CF6); border:none; border-radius:10px; color:white; font-size:15px; font-weight:700; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; transition:all 0.2s;">
                    <i class="fas fa-shield-halved"></i> Generate DPC Provisioning QR
                </button>
                <p style="font-size:12px; color:var(--text-muted); text-align:center; margin-top:12px;">
                    <i class="fas fa-rotate-left"></i> The phone must be freshly factory-reset. Tap the screen 6 times on the welcome screen, then scan this QR.
                </p>
            </div>
        </div>

        <!-- QR code display area -->
        <div id="qr-output" class="card" style="display:none; padding:28px; text-align:center;">
            <div id="qr-canvas" style="display:flex; justify-content:center; margin-bottom:20px;">
                <!-- QR renders here -->
            </div>
            <div id="qr-type-badge" style="margin-bottom:8px;"></div>
            <div id="qr-link-display" style="font-family:monospace; font-size:12px; color:var(--text-muted); background:var(--bg-tertiary); padding:10px 14px; border-radius:8px; word-break:break-all; margin-bottom:16px; border:1px solid var(--border-color);"></div>

            <div style="display:flex; gap:10px; flex-wrap:wrap; justify-content:center;">
                <button onclick="copyLink()" id="btn-copy"
                        style="flex:1; min-width:120px; padding:11px 16px; background:transparent; border:1px solid var(--border-color); border-radius:8px; color:var(--text-primary); font-size:13px; font-weight:600; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:6px; transition:all 0.2s;">
                    <i class="fas fa-copy"></i> Copy Link
                </button>
                <button onclick="downloadQR()" id="btn-dl-qr"
                        style="flex:1; min-width:120px; padding:11px 16px; background:transparent; border:1px solid var(--border-color); border-radius:8px; color:var(--text-primary); font-size:13px; font-weight:600; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:6px; transition:all 0.2s;">
                    <i class="fas fa-download"></i> Save QR Image
                </button>
                <a id="btn-open-enroll" href="#" target="_blank"
                   style="flex:1; min-width:120px; padding:11px 16px; background:transparent; border:1px solid var(--border-color); border-radius:8px; color:var(--text-primary); font-size:13px; font-weight:600; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:6px; transition:all 0.2s; text-decoration:none;">
                    <i class="fas fa-external-link-alt"></i> Open Page
                </a>
            </div>
        </div>

    </div>

    <!-- Right column: Token history -->
    <div class="card">
        <div class="card-header" style="display:flex; align-items:center; justify-content:space-between;">
            <h3 class="card-title"><i class="fas fa-history"></i> Enrollment History</h3>
            <span style="font-size:12px; color:var(--text-muted);">Last 30 tokens</span>
        </div>

        <?php if (empty($tokens)): ?>
        <div class="empty-state" style="padding:40px;">
            <i class="fas fa-qrcode"></i>
            <h3>No enrollments yet</h3>
            <p>Generate your first QR code to start enrolling devices wirelessly.</p>
        </div>
        <?php else: ?>
        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:13px;">
                <thead>
                    <tr style="color:var(--text-muted); font-weight:600; text-transform:uppercase; font-size:11px; letter-spacing:0.05em; border-bottom:1px solid var(--border-color);">
                        <th style="padding:12px 16px; text-align:left;">Token</th>
                        <th style="padding:12px 8px; text-align:left;">Policy</th>
                        <th style="padding:12px 8px; text-align:left;">Status</th>
                        <th style="padding:12px 8px; text-align:left;">Expires</th>
                        <th style="padding:12px 8px; text-align:left;">Device</th>
                        <th style="padding:12px 8px; text-align:left;"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tokens as $t):
                        $enrollLink = APP_URL . '/enroll?token=' . urlencode($t['token']);
                        $status = $t['token_status'];
                        $statusColors = [
                            'active'  => ['bg' => 'rgba(16,185,129,0.15)',  'color' => '#10B981'],
                            'used'    => ['bg' => 'rgba(99,102,241,0.15)',  'color' => '#818CF8'],
                            'expired' => ['bg' => 'rgba(100,116,139,0.15)', 'color' => '#64748B'],
                        ];
                        $sc = $statusColors[$status] ?? $statusColors['expired'];
                    ?>
                    <tr style="border-bottom:1px solid var(--border-color); transition:background 0.15s;" onmouseover="this.style.background='var(--bg-tertiary)'" onmouseout="this.style.background=''">
                        <td style="padding:12px 16px; font-family:monospace; color:var(--text-primary);"><?= substr($t['token'], 0, 8) ?>...</td>
                        <td style="padding:12px 8px; color:var(--text-secondary);"><?= sanitize($t['policy_name'] ?: '—') ?></td>
                        <td style="padding:12px 8px;">
                            <span style="padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; background:<?= $sc['bg'] ?>; color:<?= $sc['color'] ?>; text-transform:uppercase; letter-spacing:0.04em;">
                                <?= $status ?>
                            </span>
                        </td>
                        <td style="padding:12px 8px; color:var(--text-muted); font-size:12px;">
                            <?= date('M j, H:i', strtotime($t['expires_at'])) ?>
                        </td>
                        <td style="padding:12px 8px; color:var(--text-muted); font-family:monospace; font-size:11px;">
                            <?= $t['used_by_imei'] ? sanitize($t['used_by_imei']) : '—' ?>
                        </td>
                        <td style="padding:12px 8px;">
                            <?php if ($status === 'active'): ?>
                            <button onclick="showExistingQR('<?= addslashes(htmlspecialchars($enrollLink)) ?>')"
                                    title="Show QR"
                                    style="padding:5px 10px; background:transparent; border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary); font-size:12px; cursor:pointer;">
                                <i class="fas fa-qrcode"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</div>

<!-- How it works info card -->
<div class="card" style="margin-top:24px;">
    <div class="card-header"><h3 class="card-title"><i class="fas fa-circle-info"></i> How Wireless Enrollment Works</h3></div>
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px,1fr)); gap:20px; padding:4px;">
        <?php
        $steps = [
            ['fas fa-qrcode', '#6366F1', 'Generate QR', 'Admin selects a policy and generates a QR code or enrollment link.'],
            ['fas fa-mobile-screen', '#8B5CF6', 'Scan & Download', 'User scans the QR with their phone camera. Browser opens and they download the MDM Agent APK.'],
            ['fas fa-download', '#10B981', 'Install App', "User taps Install on the downloaded APK. Android asks to allow installs from browser — user approves once."],
            ['fas fa-shield-check', '#F59E0B', 'Auto-Configure', 'App opens, reads the token automatically, calls the server, and MDM activates — zero inputs needed.'],
        ];
        foreach ($steps as $i => [$icon, $color, $title, $desc]):
        ?>
        <div style="display:flex; gap:14px; align-items:flex-start; padding:4px;">
            <div style="width:40px; height:40px; min-width:40px; border-radius:10px; background:<?= $color ?>22; border:1px solid <?= $color ?>44; display:flex; align-items:center; justify-content:center; color:<?= $color ?>; font-size:16px;">
                <i class="<?= $icon ?>"></i>
            </div>
            <div>
                <div style="font-weight:700; color:var(--text-primary); margin-bottom:4px; font-size:13px;"><?= $i + 1 ?>. <?= $title ?></div>
                <div style="font-size:12px; color:var(--text-muted); line-height:1.5;"><?= $desc ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Load QR code library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<script>
const APP_URL = '<?= APP_URL ?>';
const APK_URL = '<?= APP_URL ?>/apk/mdm-agent.apk';
const DPC_CERT = '<?= DPC_CERT_SHA256 ?>';

let currentQrUrl = '';
let currentQrInstance = null;

/* ── Tab switching ─────────────────────────── */
function switchTab(tab) {
    const isRegular = tab === 'regular';
    document.getElementById('panel-regular').style.display = isRegular ? 'block' : 'none';
    document.getElementById('panel-dpc').style.display     = isRegular ? 'none'  : 'block';
    document.getElementById('tab-regular').style.background = isRegular ? 'var(--accent-color)' : 'transparent';
    document.getElementById('tab-regular').style.color      = isRegular ? 'white' : 'var(--text-muted)';
    document.getElementById('tab-dpc').style.background    = isRegular ? 'transparent' : 'linear-gradient(135deg,#6366F1,#8B5CF6)';
    document.getElementById('tab-dpc').style.color         = isRegular ? 'var(--text-muted)' : 'white';
    document.getElementById('qr-output').style.display = 'none';
}

/* ── Render QR code ─────────────────────────── */
function renderQR(url, badgeHtml) {
    currentQrUrl = url;
    const container = document.getElementById('qr-canvas');
    container.innerHTML = '';

    currentQrInstance = new QRCode(container, {
        text: url,
        width: 220,
        height: 220,
        colorDark: '#0A0F1E',
        colorLight: '#FFFFFF',
        correctLevel: QRCode.CorrectLevel.M
    });

    document.getElementById('qr-link-display').textContent = url;
    document.getElementById('qr-type-badge').innerHTML = badgeHtml;
    document.getElementById('btn-open-enroll').href = url;

    const out = document.getElementById('qr-output');
    out.style.display = 'block';
    out.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

/* ── Generate regular enrollment QR ────────── */
async function generateQR() {
    const btn = document.getElementById('btn-generate');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
    btn.disabled = true;

    try {
        const policyId = document.getElementById('policy-select').value;
        const label    = document.getElementById('token-label').value.trim();

        const resp = await fetch(APP_URL + '/api/enrollment/generate-token.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ policy_id: policyId || null, label: label || null })
        });
        const data = await resp.json();

        if (data.success) {
            const enrollUrl = data.enrollment_url;
            renderQR(enrollUrl, `
                <span style="padding:4px 12px; border-radius:20px; background:rgba(16,185,129,0.15); color:#10B981; font-size:12px; font-weight:700; border:1px solid rgba(16,185,129,0.3);">
                    <i class="fas fa-check-circle"></i> Enrollment QR · Valid 24h
                </span>`);
            showToast('QR code generated successfully!', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(data.message || 'Failed to generate token', 'error');
        }
    } catch (e) {
        showToast('Network error: ' + e.message, 'error');
    } finally {
        btn.innerHTML = '<i class="fas fa-qrcode"></i> Generate Enrollment QR';
        btn.disabled = false;
    }
}

/* ── Generate DPC provisioning QR ─────────── */
async function generateDpcQR() {
    const btn = document.getElementById('btn-generate-dpc');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
    btn.disabled = true;

    try {
        const policyId = document.getElementById('dpc-policy-select').value;

        // First generate an enrollment token to embed in the DPC extras
        const resp = await fetch(APP_URL + '/api/enrollment/generate-token.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ policy_id: policyId || null, label: 'DPC Provisioning' })
        });
        const data = await resp.json();

        if (!data.success) {
            showToast(data.message || 'Failed to generate token', 'error');
            return;
        }

        // Build DPC provisioning QR JSON (Android Enterprise format)
        const dpcJson = {
            "android.app.extra.PROVISIONING_DEVICE_ADMIN_COMPONENT_NAME":
                "com.mdm.agent/com.mdm.agent.DeviceAdminReceiver",
            "android.app.extra.PROVISIONING_DEVICE_ADMIN_PACKAGE_DOWNLOAD_LOCATION": APK_URL,
            "android.app.extra.PROVISIONING_DEVICE_ADMIN_SIGNATURE_CHECKSUM": DPC_CERT,
            "android.app.extra.PROVISIONING_SKIP_ENCRYPTION": false,
            "android.app.extra.PROVISIONING_LEAVE_ALL_SYSTEM_APPS_ENABLED": false,
            "android.app.extra.PROVISIONING_ADMIN_EXTRAS_BUNDLE": {
                "enrollment_token": data.token,
                "server_url": APP_URL
            }
        };
        const dpcString = JSON.stringify(dpcJson);

        renderQR(dpcString, `
            <span style="padding:4px 12px; border-radius:20px; background:rgba(99,102,241,0.15); color:#818CF8; font-size:12px; font-weight:700; border:1px solid rgba(99,102,241,0.3);">
                <i class="fas fa-shield-halved"></i> DPC Provisioning QR · Scan at Setup Wizard
            </span>`);

        // Override the open/copy link to show the JSON
        document.getElementById('btn-open-enroll').style.display = 'none';
        showToast('DPC Provisioning QR generated! Scan at Android setup wizard.', 'success');

    } catch (e) {
        showToast('Network error: ' + e.message, 'error');
    } finally {
        btn.innerHTML = '<i class="fas fa-shield-halved"></i> Generate DPC Provisioning QR';
        btn.disabled = false;
    }
}

/* ── Show existing token QR from history ──── */
function showExistingQR(url) {
    renderQR(url, `
        <span style="padding:4px 12px; border-radius:20px; background:rgba(16,185,129,0.15); color:#10B981; font-size:12px; font-weight:700; border:1px solid rgba(16,185,129,0.3);">
            <i class="fas fa-check-circle"></i> Active Enrollment QR
        </span>`);
}

/* ── Copy link ─────────────────────────────── */
function copyLink() {
    if (!currentQrUrl) return;
    navigator.clipboard.writeText(currentQrUrl).then(() => {
        const btn = document.getElementById('btn-copy');
        btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
        btn.style.borderColor = 'var(--success-color)';
        btn.style.color = 'var(--success-color)';
        setTimeout(() => {
            btn.innerHTML = '<i class="fas fa-copy"></i> Copy Link';
            btn.style.borderColor = '';
            btn.style.color = '';
        }, 2000);
    });
}

/* ── Download QR as PNG ───────────────────── */
function downloadQR() {
    const canvas = document.querySelector('#qr-canvas canvas');
    const img    = document.querySelector('#qr-canvas img');
    if (canvas) {
        const a = document.createElement('a');
        a.download = 'mdm-enrollment-qr.png';
        a.href = canvas.toDataURL('image/png');
        a.click();
    } else if (img) {
        const a = document.createElement('a');
        a.download = 'mdm-enrollment-qr.png';
        a.href = img.src;
        a.click();
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
