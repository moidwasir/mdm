<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon"><i class="fas fa-shield-halved"></i></div>
        <div>
            <h2>MDM Center</h2>
            <span>Control Panel</span>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section">
            <div class="nav-section-title">Overview</div>
            <a href="dashboard.php" class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                <i class="fas fa-chart-pie"></i> Dashboard
            </a>
            <a href="analytics.php" class="nav-item <?= $currentPage === 'analytics' ? 'active' : '' ?>">
                <i class="fas fa-chart-line"></i> Analytics
            </a>
        </div>
        <div class="nav-section">
            <div class="nav-section-title">Management</div>
            <a href="devices.php" class="nav-item <?= $currentPage === 'devices' ? 'active' : '' ?>">
                <i class="fas fa-mobile-screen"></i> Devices
            </a>
            <a href="add-device.php" class="nav-item <?= $currentPage === 'add-device' ? 'active' : '' ?>">
                <i class="fas fa-barcode"></i> Add Device
            </a>
            <a href="enrollment.php" class="nav-item <?= $currentPage === 'enrollment' ? 'active' : '' ?>">
                <i class="fas fa-qrcode"></i> Enrollment QR
            </a>
            <a href="users.php" class="nav-item <?= $currentPage === 'users' ? 'active' : '' ?>">
                <i class="fas fa-users"></i> Users
            </a>
            <a href="policies.php" class="nav-item <?= $currentPage === 'policies' ? 'active' : '' ?>">
                <i class="fas fa-sliders"></i> Policies
            </a>
        </div>

        <div class="nav-section">
            <div class="nav-section-title">Communication</div>
            <a href="chat-monitor.php" class="nav-item <?= $currentPage === 'chat-monitor' ? 'active' : '' ?>">
                <i class="fas fa-comments"></i> Chat Monitor
            </a>
        </div>
        <div class="nav-section">
            <div class="nav-section-title">System</div>
            <a href="logs.php" class="nav-item <?= $currentPage === 'logs' ? 'active' : '' ?>">
                <i class="fas fa-clock-rotate-left"></i> Logs
            </a>
            <a href="settings.php" class="nav-item <?= $currentPage === 'settings' ? 'active' : '' ?>">
                <i class="fas fa-gear"></i> Settings
            </a>
        </div>
    </nav>
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="user-avatar"><?= strtoupper(substr($currentAdmin['full_name'], 0, 1)) ?></div>
            <div class="user-info">
                <div class="user-name"><?= sanitize($currentAdmin['full_name']) ?></div>
                <div class="user-role"><?= ucfirst($currentAdmin['role']) ?></div>
            </div>
            <a href="<?= APP_URL ?>/api/auth/logout.php" class="btn-icon" title="Logout" style="color:var(--text-muted);background:transparent;border:none;">
                <i class="fas fa-right-from-bracket"></i>
            </a>
        </div>
    </div>
</aside>
