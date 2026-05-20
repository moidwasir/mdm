<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';

if (isset($_SESSION['admin_id'])) {
    header('Location: ' . APP_URL . '/admin/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM admins WHERE (username = ? OR email = ?) AND is_active = 1");
        $stmt->execute([$username, $username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            $_SESSION['admin_id'] = $admin['id'];
            $db->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?")->execute([$admin['id']]);
            header('Location: ' . APP_URL . '/admin/dashboard.php');
            exit;
        } else {
            $error = 'Invalid credentials';
        }
    } else {
        $error = 'Please fill in all fields';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MDM Control Center - Login</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .login-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-primary);
            position: relative;
            overflow: hidden;
        }
        .login-wrapper::before {
            content: '';
            position: absolute;
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(99,102,241,0.12) 0%, transparent 70%);
            top: -100px; right: -100px;
            border-radius: 50%;
        }
        .login-wrapper::after {
            content: '';
            position: absolute;
            width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(139,92,246,0.08) 0%, transparent 70%);
            bottom: -80px; left: -80px;
            border-radius: 50%;
        }
        .login-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-xl);
            padding: 48px 40px;
            width: 100%;
            max-width: 420px;
            position: relative;
            z-index: 1;
            box-shadow: var(--shadow-lg);
        }
        .login-logo {
            text-align: center;
            margin-bottom: 36px;
        }
        .login-logo .logo-icon {
            width: 64px; height: 64px;
            background: var(--gradient-1);
            border-radius: var(--radius-md);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 16px;
            font-size: 1.8rem;
            color: #fff;
            box-shadow: 0 8px 25px rgba(99,102,241,0.3);
        }
        .login-logo h1 { font-size: 1.5rem; font-weight: 800; margin-bottom: 4px; }
        .login-logo p { color: var(--text-muted); font-size: 0.85rem; }
        .login-input-group {
            position: relative;
            margin-bottom: 20px;
        }
        .login-input-group i {
            position: absolute;
            left: 14px; top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            transition: var(--transition);
        }
        .login-input-group input {
            width: 100%;
            padding: 13px 14px 13px 42px;
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            color: var(--text-primary);
            font-size: 0.9rem;
            outline: none;
            transition: var(--transition);
        }
        .login-input-group input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
        }
        .login-input-group input:focus + i,
        .login-input-group input:focus ~ i { color: var(--accent-light); }
        .login-btn {
            width: 100%;
            padding: 14px;
            background: var(--gradient-1);
            border: none;
            border-radius: var(--radius-sm);
            color: #fff;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 8px;
        }
        .login-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(99,102,241,0.4); }
        .login-error {
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.2);
            color: var(--danger);
            padding: 10px 14px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            font-size: 0.85rem;
            display: flex; align-items: center; gap: 8px;
        }
        .login-footer {
            text-align: center;
            margin-top: 24px;
            color: var(--text-muted);
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-logo">
                <div class="logo-icon"><i class="fas fa-shield-halved"></i></div>
                <h1>MDM Control Center</h1>
                <p>Mobile Device Management System</p>
            </div>

            <?php if ($error): ?>
                <div class="login-error">
                    <i class="fas fa-circle-exclamation"></i>
                    <?= sanitize($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <div class="login-input-group">
                    <input type="text" name="username" placeholder="Username or Email" required value="<?= sanitize($_POST['username'] ?? '') ?>" id="login-username">
                    <i class="fas fa-user"></i>
                </div>
                <div class="login-input-group">
                    <input type="password" name="password" placeholder="Password" required id="login-password">
                    <i class="fas fa-lock"></i>
                </div>
                <button type="submit" class="login-btn" id="login-submit">
                    <i class="fas fa-right-to-bracket"></i> Sign In
                </button>
            </form>

            <div class="login-footer">
                <p>&copy; <?= date('Y') ?> MDM Control Center v<?= APP_VERSION ?></p>
            </div>
        </div>
    </div>
</body>
</html>
