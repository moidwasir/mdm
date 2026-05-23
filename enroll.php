<?php
/**
 * Wireless MDM Enrollment Landing Page
 * Users are sent here by scanning the admin-generated QR code.
 * Validates token and guides user through zero-touch APK installation.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';

$token     = trim($_GET['token'] ?? '');
$tokenValid = false;
$tokenData  = null;
$error      = null;

if (empty($token)) {
    $error = 'No enrollment token provided. Please scan the QR code given by your IT administrator.';
} else {
    try {
        $db = getDB();
        $stmt = $db->prepare(
            "SELECT et.*, p.name AS policy_name FROM enrollment_tokens et
             LEFT JOIN policies p ON et.policy_id = p.id
             WHERE et.token = ? AND et.is_used = 0 AND et.expires_at > NOW()"
        );
        $stmt->execute([$token]);
        $tokenData = $stmt->fetch();
        if ($tokenData) {
            $tokenValid = true;
        } else {
            // Check if it was used already
            $usedStmt = $db->prepare("SELECT used_by_imei FROM enrollment_tokens WHERE token = ? AND is_used = 1");
            $usedStmt->execute([$token]);
            $usedToken = $usedStmt->fetch();
            if ($usedToken) {
                $error = 'This enrollment link has already been used. Each QR code can only enroll one device. Ask your administrator to generate a new QR code.';
            } else {
                $error = 'This enrollment link has expired or is invalid. Please ask your IT administrator for a new QR code.';
            }
        }
    } catch (Exception $e) {
        $error = 'Unable to verify enrollment token. Please try again or contact your administrator.';
    }
}

$apkUrl    = APP_URL . '/apk/mdm-agent.apk';
$deepLink  = 'mdm://enroll?token=' . urlencode($token);
$enrollUrl = APP_URL . '/enroll.php?token=' . urlencode($token);
$expiresIn = $tokenValid ? max(0, strtotime($tokenData['expires_at']) - time()) : 0;
$expiresHours = floor($expiresIn / 3600);
$expiresMin   = floor(($expiresIn % 3600) / 60);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>MDM Device Enrollment</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --bg-primary: #0A0F1E;
            --bg-card: rgba(255,255,255,0.04);
            --bg-card-hover: rgba(255,255,255,0.07);
            --border: rgba(255,255,255,0.09);
            --accent: #6366F1;
            --accent-light: #818CF8;
            --accent-glow: rgba(99,102,241,0.3);
            --green: #10B981;
            --green-glow: rgba(16,185,129,0.25);
            --red: #EF4444;
            --yellow: #F59E0B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --text-muted: #64748B;
            --radius: 16px;
            --radius-sm: 10px;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            overflow-x: hidden;
        }
        /* Animated gradient background */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 80% 50% at 20% 20%, rgba(99,102,241,0.15) 0%, transparent 60%),
                radial-gradient(ellipse 60% 60% at 80% 80%, rgba(16,185,129,0.08) 0%, transparent 60%);
            pointer-events: none;
            z-index: 0;
        }
        .page {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 24px 16px 60px;
        }

        /* ── Header ── */
        .brand-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 40px;
            padding-top: 8px;
        }
        .brand-icon {
            width: 44px; height: 44px;
            background: linear-gradient(135deg, var(--accent), #8B5CF6);
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
            box-shadow: 0 0 24px var(--accent-glow);
        }
        .brand-name { font-size: 20px; font-weight: 700; color: var(--text-primary); }
        .brand-sub  { font-size: 12px; color: var(--text-muted); font-weight: 400; }

        /* ── Error state ── */
        .error-card {
            width: 100%; max-width: 420px;
            background: rgba(239,68,68,0.08);
            border: 1px solid rgba(239,68,68,0.25);
            border-radius: var(--radius);
            padding: 32px 28px;
            text-align: center;
        }
        .error-icon { font-size: 48px; color: var(--red); margin-bottom: 20px; }
        .error-title { font-size: 20px; font-weight: 700; color: var(--red); margin-bottom: 12px; }
        .error-msg { color: var(--text-secondary); line-height: 1.6; font-size: 14px; }

        /* ── Main enrollment card ── */
        .enrollment-card {
            width: 100%; max-width: 480px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            backdrop-filter: blur(20px);
            overflow: hidden;
        }

        /* Token status banner */
        .token-banner {
            background: linear-gradient(135deg, rgba(16,185,129,0.15), rgba(16,185,129,0.05));
            border-bottom: 1px solid rgba(16,185,129,0.2);
            padding: 14px 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .token-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: var(--green);
            box-shadow: 0 0 8px var(--green);
            animation: pulse-dot 2s infinite;
        }
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(0.8); }
        }
        .token-text { font-size: 13px; color: var(--green); font-weight: 600; }
        .token-expiry { font-size: 12px; color: var(--text-muted); margin-left: auto; }

        .card-body { padding: 32px 28px; }
        .card-title {
            font-size: 22px; font-weight: 800;
            background: linear-gradient(135deg, var(--text-primary), var(--accent-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 6px;
        }
        .card-subtitle { color: var(--text-secondary); font-size: 14px; line-height: 1.5; margin-bottom: 32px; }

        /* ── Steps ── */
        .steps { display: flex; flex-direction: column; gap: 0; }
        .step {
            display: flex;
            gap: 16px;
            position: relative;
        }
        .step:not(:last-child)::after {
            content: '';
            position: absolute;
            left: 19px;
            top: 48px;
            bottom: -4px;
            width: 2px;
            background: linear-gradient(to bottom, var(--accent), transparent);
            opacity: 0.3;
        }
        .step-num {
            width: 40px; height: 40px;
            min-width: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), #8B5CF6);
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 15px;
            box-shadow: 0 0 16px var(--accent-glow);
        }
        .step-num.done {
            background: linear-gradient(135deg, var(--green), #059669);
            box-shadow: 0 0 16px var(--green-glow);
        }
        .step-content { flex: 1; padding-bottom: 28px; }
        .step-label {
            font-size: 15px; font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        .step-desc {
            font-size: 13px; color: var(--text-secondary);
            line-height: 1.5; margin-bottom: 12px;
        }

        /* ── Download button ── */
        .btn-download {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 16px 24px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--accent), #8B5CF6);
            color: white;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: 0 4px 24px var(--accent-glow), 0 0 0 0 var(--accent-glow);
            margin-bottom: 8px;
        }
        .btn-download:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 32px var(--accent-glow), 0 0 0 4px rgba(99,102,241,0.1);
        }
        .btn-download:active { transform: translateY(0); }
        .btn-download i { font-size: 18px; }
        .download-size { font-size: 12px; color: var(--text-muted); text-align: center; }

        /* ── Open app button ── */
        .btn-open {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 14px 24px;
            border: 1px solid rgba(16,185,129,0.4);
            border-radius: 12px;
            background: rgba(16,185,129,0.08);
            color: var(--green);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .btn-open:hover {
            background: rgba(16,185,129,0.15);
            border-color: rgba(16,185,129,0.6);
        }

        /* ── Allow install tip ── */
        .tip-box {
            background: rgba(245,158,11,0.08);
            border: 1px solid rgba(245,158,11,0.2);
            border-radius: 10px;
            padding: 14px 16px;
            margin-top: 4px;
            display: flex;
            gap: 10px;
            align-items: flex-start;
        }
        .tip-box i { color: var(--yellow); font-size: 15px; margin-top: 1px; }
        .tip-box p { font-size: 12px; color: var(--text-secondary); line-height: 1.5; }
        .tip-box strong { color: var(--yellow); }

        /* ── Policy badge ── */
        .policy-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 20px;
            background: rgba(99,102,241,0.12);
            border: 1px solid rgba(99,102,241,0.25);
            color: var(--accent-light);
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        /* ── Footer security note ── */
        .security-footer {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }
        .security-note {
            font-size: 12px;
            color: var(--text-muted);
            text-align: center;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .security-note i { color: var(--green); }

        /* ── Progress states ── */
        .progress-bar-wrap {
            height: 4px;
            background: rgba(255,255,255,0.06);
            border-radius: 2px;
            overflow: hidden;
            margin-top: 8px;
        }
        .progress-bar {
            height: 100%;
            border-radius: 2px;
            background: linear-gradient(90deg, var(--accent), var(--green));
            width: 0%;
            transition: width 0.4s ease;
        }

        .separator { width: 100%; max-width: 480px; text-align: center; color: var(--text-muted); font-size: 12px; margin: 24px 0 0; }
    </style>
</head>
<body>
<div class="page">

    <!-- Brand Header -->
    <div class="brand-header">
        <div class="brand-icon"><i class="fas fa-shield-halved"></i></div>
        <div>
            <div class="brand-name">MDM Control Center</div>
            <div class="brand-sub">Enterprise Device Management</div>
        </div>
    </div>

    <?php if ($error): ?>
    <!-- Error state -->
    <div class="error-card">
        <div class="error-icon"><i class="fas fa-circle-xmark"></i></div>
        <div class="error-title">Enrollment Failed</div>
        <div class="error-msg"><?= htmlspecialchars($error) ?></div>
    </div>

    <?php else: ?>
    <!-- Valid token — enrollment wizard -->
    <div class="enrollment-card">

        <!-- Active token banner -->
        <div class="token-banner">
            <div class="token-dot"></div>
            <span class="token-text">Enrollment Link Active</span>
            <?php if ($expiresHours > 0 || $expiresMin > 0): ?>
            <span class="token-expiry">
                <i class="fas fa-clock"></i>
                Expires in <?= $expiresHours > 0 ? "{$expiresHours}h {$expiresMin}m" : "{$expiresMin}m" ?>
            </span>
            <?php endif; ?>
        </div>

        <div class="card-body">
            <?php if (!empty($tokenData['policy_name'])): ?>
            <div class="policy-badge">
                <i class="fas fa-shield-check"></i>
                Policy: <?= htmlspecialchars($tokenData['policy_name']) ?>
            </div>
            <?php endif; ?>

            <div class="card-title">Set Up MDM on This Device</div>
            <div class="card-subtitle">
                Follow these 3 simple steps to configure this device for secure enterprise management.
                This takes less than 2 minutes.
            </div>

            <div class="steps">

                <!-- Step 1: Download -->
                <div class="step" id="step-1">
                    <div class="step-num" id="num-1">1</div>
                    <div class="step-content">
                        <div class="step-label">Download MDM Agent</div>
                        <div class="step-desc">Tap the button below to download the MDM Agent application on this device.</div>

                        <a href="<?= htmlspecialchars($apkUrl . '?token=' . urlencode($token)) ?>"
                           id="btn-apk-download"
                           class="btn-download"
                           onclick="onDownloadClick()">
                            <i class="fas fa-download"></i>
                            Download MDM Agent
                        </a>
                        <div class="download-size"><i class="fas fa-file-zipper"></i> APK · ~1.4 MB · Verified Secure</div>

                        <div class="tip-box" style="margin-top:12px;">
                            <i class="fas fa-triangle-exclamation"></i>
                            <p>After tapping Download, your browser may ask you to <strong>Allow installing from unknown sources</strong>. Tap <strong>Settings → Allow</strong> to proceed.</p>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Install -->
                <div class="step" id="step-2">
                    <div class="step-num" id="num-2">2</div>
                    <div class="step-content">
                        <div class="step-label">Install the App</div>
                        <div class="step-desc">
                            Once downloaded, open the file from your notifications or Downloads folder.
                            Tap <strong>Install</strong> when prompted.
                        </div>
                        <div class="tip-box">
                            <i class="fas fa-info-circle"></i>
                            <p>If you see <strong>"Install blocked"</strong>, go to <strong>Settings → Apps → Special app access → Install unknown apps</strong> and allow your browser app.</p>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Activate -->
                <div class="step" id="step-3">
                    <div class="step-num" id="num-3">3</div>
                    <div class="step-content">
                        <div class="step-label">MDM Activates Automatically</div>
                        <div class="step-desc">
                            Open the MDM Agent app. It will connect to your organization's server
                            and configure itself automatically — no inputs required.
                        </div>

                        <!-- Deep link button to open app directly with token -->
                        <a href="<?= htmlspecialchars($deepLink) ?>" id="btn-open-app" class="btn-open">
                            <i class="fas fa-mobile-screen"></i>
                            Open MDM Agent App
                        </a>

                        <div class="progress-bar-wrap" id="progress-wrap" style="display:none;">
                            <div class="progress-bar" id="progress-bar"></div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Security footer -->
            <div class="security-footer">
                <div class="security-note">
                    <i class="fas fa-lock"></i>
                    This enrollment link is single-use and expires automatically.
                </div>
                <div class="security-note">
                    <i class="fas fa-shield-check"></i>
                    Your device will be managed by your organization's IT team.
                </div>
            </div>

        </div>
    </div>

    <?php endif; ?>

    <div class="separator">
        Powered by MDM Control Center &nbsp;·&nbsp; Enterprise Device Management
    </div>

</div>

<script>
    const TOKEN = '<?= htmlspecialchars(addslashes($token)) ?>';

    function onDownloadClick() {
        const btn = document.getElementById('btn-apk-download');
        setTimeout(() => {
            btn.innerHTML = '<i class="fas fa-check"></i> Downloading...';
            btn.style.background = 'linear-gradient(135deg, #10B981, #059669)';
            document.getElementById('num-1').classList.add('done');
            document.getElementById('num-1').innerHTML = '<i class="fas fa-check"></i>';
        }, 800);
    }

    // Try to open the app via deep-link — if it opens, great; if not, user installs first
    document.getElementById('btn-open-app')?.addEventListener('click', function(e) {
        const progressWrap = document.getElementById('progress-wrap');
        const progressBar  = document.getElementById('progress-bar');
        if (progressWrap) {
            progressWrap.style.display = 'block';
            let width = 0;
            const interval = setInterval(() => {
                width += 5;
                progressBar.style.width = width + '%';
                if (width >= 100) clearInterval(interval);
            }, 100);
        }
    });

    // Auto-try deep link on page load if token is present (for re-visits)
    window.addEventListener('load', function() {
        const params = new URLSearchParams(window.location.search);
        const token = params.get('token');
        if (token && /Android/i.test(navigator.userAgent)) {
            // Try to launch MDM app silently
            const iframe = document.createElement('iframe');
            iframe.style.display = 'none';
            iframe.src = 'mdm://enroll?token=' + encodeURIComponent(token);
            document.body.appendChild(iframe);
            setTimeout(() => document.body.removeChild(iframe), 2000);
        }
    });
</script>
</body>
</html>
