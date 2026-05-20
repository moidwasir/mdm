<?php
$pageTitle = 'Settings';
require_once __DIR__ . '/../includes/auth-check.php';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        
        $admin = $db->prepare("SELECT password_hash FROM admins WHERE id = ?");
        $admin->execute([$currentAdmin['id']]);
        $adminData = $admin->fetch();
        
        if (!password_verify($current, $adminData['password_hash'])) {
            $error = 'Current password is incorrect';
        } elseif (strlen($new) < 6) {
            $error = 'New password must be at least 6 characters';
        } elseif ($new !== $confirm) {
            $error = 'Passwords do not match';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $db->prepare("UPDATE admins SET password_hash = ? WHERE id = ?")->execute([$hash, $currentAdmin['id']]);
            $success = 'Password changed successfully';
        }
    }

    if ($_POST['action'] === 'upload_apk') {
        $appName     = sanitize($_POST['app_name']     ?? '');
        $packageName = sanitize($_POST['package_name'] ?? '');
        $versionName = sanitize($_POST['version_name'] ?? '');
        $versionCode = (int)($_POST['version_code']    ?? 0);

        if (empty($_FILES['apk_file']['name'])) {
            $error = 'Please select an APK file to upload';
        } else {
            $file = $_FILES['apk_file'];
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if ($ext !== 'apk') {
                $error = 'Invalid file format. Only .apk files are allowed.';
            } elseif ($file['error'] !== UPLOAD_ERR_OK) {
                $uploadErrors = [
                    UPLOAD_ERR_INI_SIZE   => 'File too large (server php.ini limit)',
                    UPLOAD_ERR_FORM_SIZE  => 'File too large (form limit)',
                    UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded',
                    UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
                    UPLOAD_ERR_NO_TMP_DIR => 'Missing temp folder',
                    UPLOAD_ERR_CANT_WRITE => 'Failed to write to disk',
                ];
                $error = $uploadErrors[$file['error']] ?? 'Upload error code: ' . $file['error'];
            } else {
                // Ensure APK directory exists and is writable
                $apkDir = realpath(__DIR__ . '/../apk/') ?: (__DIR__ . '/../apk/');
                if (!is_dir($apkDir)) {
                    mkdir($apkDir, 0777, true);
                    chmod($apkDir, 0777);
                }

                $targetFilename = ($packageName === 'com.mdm.agent') ? 'mdm-agent.apk' : 'chat-app.apk';
                $destPath       = rtrim($apkDir, '/') . '/' . $targetFilename;

                // Try move_uploaded_file first, then copy+unlink fallback
                $moved = move_uploaded_file($file['tmp_name'], $destPath);
                if (!$moved && file_exists($file['tmp_name'])) {
                    $moved = copy($file['tmp_name'], $destPath);
                    if ($moved) @unlink($file['tmp_name']);
                }

                if ($moved) {
                    chmod($destPath, 0644);
                    $url = APP_URL . '/apk/' . $targetFilename;

                    // Insert or update DB version record
                    $db->prepare("
                        INSERT INTO app_versions (app_name, package_name, version_name, version_code, apk_url)
                        VALUES (?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                            app_name = VALUES(app_name),
                            version_name = VALUES(version_name),
                            version_code = VALUES(version_code),
                            apk_url = VALUES(apk_url)
                    ")->execute([$appName, $packageName, $versionName, $versionCode, $url]);

                    $success = "✅ APK uploaded! v{$versionName} — devices will auto-update on next heartbeat.";
                    logAction('ota_upload', "Uploaded $appName v{$versionName} ({$versionCode}) → {$destPath}");
                } else {
                    $dirWritable = is_writable($apkDir) ? 'writable' : 'NOT writable';
                    $tmpExists   = file_exists($file['tmp_name']) ? 'exists' : 'missing';
                    $error = "Failed to save APK. Dir: {$apkDir} ({$dirWritable}), Temp file: {$tmpExists}. Try: sudo chmod 777 {$apkDir}";
                }
            }
        }
    }
}

// Fetch currently uploaded app versions
$appVersions = $db->query("SELECT * FROM app_versions ORDER BY id DESC")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-gear"></i> Settings</h1>
    <p>System configuration, security settings, and OTA APK distributions</p>
</div>

<?php if (isset($error)): ?>
    <div style="padding:12px 16px;background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.2);border-radius:var(--radius-sm);color:var(--danger);margin-bottom:20px;font-size:0.85rem;">
        <i class="fas fa-exclamation-circle"></i> <?= sanitize($error) ?>
    </div>
<?php endif; ?>

<?php if (isset($success)): ?>
    <div style="padding:12px 16px;background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.2);border-radius:var(--radius-sm);color:var(--success);margin-bottom:20px;font-size:0.85rem;">
        <i class="fas fa-check-circle"></i> <?= sanitize($success) ?>
    </div>
<?php endif; ?>

<div class="grid-2">
    <!-- Account Settings -->
    <div class="card">
        <div class="card-header"><h3 class="card-title">Profile Account Info</h3></div>
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:24px;">
            <div style="width:64px;height:64px;border-radius:50%;background:var(--gradient-1);display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:700;color:#fff;">
                <?= strtoupper(substr($currentAdmin['full_name'], 0, 1)) ?>
            </div>
            <div>
                <div style="font-size:1.1rem;font-weight:700;"><?= sanitize($currentAdmin['full_name']) ?></div>
                <div style="color:var(--text-muted);font-size:0.85rem;">@<?= sanitize($currentAdmin['username']) ?> · <?= sanitize($currentAdmin['email']) ?></div>
                <div style="margin-top:4px;"><span class="badge badge-info"><?= ucfirst($currentAdmin['role']) ?></span></div>
            </div>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="change_password">
            <div class="form-group"><label class="form-label">Current Password</label><input type="password" name="current_password" class="form-control" required></div>
            <div class="form-group"><label class="form-label">New Password</label><input type="password" name="new_password" class="form-control" required minlength="6"></div>
            <div class="form-group"><label class="form-label">Confirm Password</label><input type="password" name="confirm_password" class="form-control" required></div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-key"></i> Update Admin Password</button>
        </form>
    </div>

    <!-- OTA APK Release Portal -->
    <div class="card">
        <div class="card-header"><h3 class="card-title"><i class="fas fa-cloud-upload-alt"></i> OTA APK Release Portal</h3></div>
        <form method="POST" enctype="multipart/form-data" style="margin-bottom: 24px;">
            <input type="hidden" name="action" value="upload_apk">
            <div class="form-group">
                <label class="form-label">App Target Type</label>
                <select name="package_name" class="form-control" required onchange="updateAppFields(this.value)">
                    <option value="com.mdm.chat">MDM Kiosk Chat App</option>
                    <option value="com.mdm.agent">MDM System Agent DPC</option>
                </select>
            </div>
            <input type="hidden" name="app_name" id="app_name_input" value="MDM Kiosk Chat App">
            
            <div class="grid-2" style="gap: 12px; margin-bottom: 0;">
                <div class="form-group">
                    <label class="form-label">Version Name</label>
                    <input type="text" name="version_name" placeholder="e.g. 1.0.0" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Version Code</label>
                    <input type="number" name="version_code" placeholder="e.g. 1" class="form-control" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Upload APK File</label>
                <input type="file" name="apk_file" accept=".apk" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-primary"><i class="fas fa-rocket"></i> Release & Push OTA Update</button>
        </form>

        <h4 style="margin-bottom: 12px; font-weight: 600;">Active OTA Catalog</h4>
        <?php if (empty($appVersions)): ?>
            <p style="color: var(--text-muted); font-size: 0.85rem;">No active OTA applications released yet.</p>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 8px;">
                <?php foreach ($appVersions as $v): ?>
                    <div style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); padding: 12px; border-radius: var(--radius-sm); display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong style="font-size: 0.9rem;"><?= sanitize($v['app_name']) ?></strong>
                            <div style="font-size: 0.75rem; color: var(--text-muted);"><?= sanitize($v['package_name']) ?></div>
                        </div>
                        <div style="text-align: right;">
                            <span class="badge badge-success">v<?= sanitize($v['version_name']) ?> (<?= $v['version_code'] ?>)</span>
                            <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 4px;">Updated: <?= timeAgo($v['updated_at']) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="margin-top:20px;">
    <div class="card-header"><h3 class="card-title">System Info</h3></div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div style="padding:12px;background:var(--bg-input);border-radius:var(--radius-sm);"><span style="color:var(--text-muted);font-size:0.8rem;">Version</span><div style="font-weight:600;"><?= APP_VERSION ?></div></div>
        <div style="padding:12px;background:var(--bg-input);border-radius:var(--radius-sm);"><span style="color:var(--text-muted);font-size:0.8rem;">PHP Version</span><div style="font-weight:600;"><?= phpversion() ?></div></div>
        <div style="padding:12px;background:var(--bg-input);border-radius:var(--radius-sm);"><span style="color:var(--text-muted);font-size:0.8rem;">Server</span><div style="font-weight:600;"><?= php_uname('s') . ' ' . php_uname('r') ?></div></div>
        <div style="padding:12px;background:var(--bg-input);border-radius:var(--radius-sm);"><span style="color:var(--text-muted);font-size:0.8rem;">Database</span><div style="font-weight:600;">MySQL <?= $db->query("SELECT VERSION()")->fetchColumn() ?></div></div>
    </div>
</div>

<script>
function updateAppFields(pkg) {
    const appNameInput = document.getElementById('app_name_input');
    if (pkg === 'com.mdm.agent') {
        appNameInput.value = 'MDM System Agent DPC';
    } else {
        appNameInput.value = 'MDM Kiosk Chat App';
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
