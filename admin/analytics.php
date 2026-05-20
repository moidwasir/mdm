<?php
$pageTitle = 'Analytics';
require_once __DIR__ . '/../includes/auth-check.php';
$db = getDB();

// --- Stats for cards ---
$totalDevices  = $db->query("SELECT COUNT(*) FROM devices")->fetchColumn();
$onlineDevices = $db->query("SELECT COUNT(*) FROM devices WHERE is_online = 1")->fetchColumn();
$totalUsers    = $db->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();
$totalMessages = $db->query("SELECT COUNT(*) FROM messages")->fetchColumn();
$totalConvs    = $db->query("SELECT COUNT(*) FROM conversations WHERE is_active = 1")->fetchColumn();
$enrolledPct   = $totalDevices > 0 ? round(($db->query("SELECT COUNT(*) FROM devices WHERE enrollment_status='enrolled'")->fetchColumn() / $totalDevices) * 100) : 0;

// --- Daily message volume (last 14 days) ---
$msgVolume = $db->query("
    SELECT DATE(created_at) as day, COUNT(*) as count
    FROM messages
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
    GROUP BY DATE(created_at)
    ORDER BY day ASC
")->fetchAll();

// --- Daily device heartbeats (last 14 days) ---
$heartbeatVolume = $db->query("
    SELECT DATE(created_at) as day, COUNT(*) as count
    FROM device_logs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
    GROUP BY DATE(created_at)
    ORDER BY day ASC
")->fetchAll();

// --- Device battery distribution ---
$batteryDist = $db->query("
    SELECT
        SUM(CASE WHEN battery_level >= 80 THEN 1 ELSE 0 END) as high,
        SUM(CASE WHEN battery_level >= 30 AND battery_level < 80 THEN 1 ELSE 0 END) as medium,
        SUM(CASE WHEN battery_level < 30 AND battery_level IS NOT NULL THEN 1 ELSE 0 END) as low,
        SUM(CASE WHEN battery_level IS NULL THEN 1 ELSE 0 END) as unknown
    FROM devices WHERE enrollment_status = 'enrolled'
")->fetch();

// --- Top 5 most active users (by message count) ---
$topUsers = $db->query("
    SELECT u.display_name, COUNT(m.id) as msg_count
    FROM messages m JOIN users u ON u.id = m.sender_id
    GROUP BY m.sender_id ORDER BY msg_count DESC LIMIT 5
")->fetchAll();

// --- Enrollment status breakdown ---
$enrollStatus = $db->query("
    SELECT enrollment_status, COUNT(*) as count FROM devices GROUP BY enrollment_status
")->fetchAll();

include __DIR__ . '/../includes/header.php';

// Build JS-safe chart data
$msgDays    = json_encode(array_column($msgVolume, 'day'));
$msgCounts  = json_encode(array_column($msgVolume, 'count'));
$hbDays     = json_encode(array_column($heartbeatVolume, 'day'));
$hbCounts   = json_encode(array_column($heartbeatVolume, 'count'));
$topNames   = json_encode(array_column($topUsers, 'display_name'));
$topCounts  = json_encode(array_column($topUsers, 'msg_count'));
$enrollLabels = json_encode(array_column($enrollStatus, 'enrollment_status'));
$enrollCounts = json_encode(array_column($enrollStatus, 'count'));
?>

<div class="page-header">
    <h1><i class="fas fa-chart-line"></i> Analytics</h1>
    <p>System-wide activity metrics and device health overview</p>
</div>

<!-- KPI Cards -->
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); margin-bottom: 28px;">
    <div class="stat-card blue">
        <div class="stat-icon"><i class="fas fa-mobile-screen"></i></div>
        <div class="stat-value"><?= $totalDevices ?></div>
        <div class="stat-label">Total Devices</div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon"><i class="fas fa-circle" style="font-size:0.7rem;"></i></div>
        <div class="stat-value"><?= $onlineDevices ?></div>
        <div class="stat-label">Online Now</div>
    </div>
    <div class="stat-card purple">
        <div class="stat-icon"><i class="fas fa-users"></i></div>
        <div class="stat-value"><?= $totalUsers ?></div>
        <div class="stat-label">Active Users</div>
    </div>
    <div class="stat-card orange">
        <div class="stat-icon"><i class="fas fa-message"></i></div>
        <div class="stat-value"><?= $totalMessages ?></div>
        <div class="stat-label">Total Messages</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon"><i class="fas fa-comments"></i></div>
        <div class="stat-value"><?= $totalConvs ?></div>
        <div class="stat-label">Conversations</div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        <div class="stat-value"><?= $enrolledPct ?>%</div>
        <div class="stat-label">Enrolled Rate</div>
    </div>
</div>

<!-- Charts Row 1 -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 24px;">
    <div class="card">
        <div class="card-header"><h3 class="card-title"><i class="fas fa-message" style="color:var(--primary-color);"></i> Message Volume (14 Days)</h3></div>
        <canvas id="msgChart" height="120"></canvas>
    </div>
    <div class="card">
        <div class="card-header"><h3 class="card-title"><i class="fas fa-mobile-screen" style="color:var(--success);"></i> Enrollment Status</h3></div>
        <canvas id="enrollChart" height="180"></canvas>
    </div>
</div>

<!-- Charts Row 2 -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
    <div class="card">
        <div class="card-header"><h3 class="card-title"><i class="fas fa-battery-half" style="color:var(--warning);"></i> Battery Distribution</h3></div>
        <canvas id="batteryChart" height="180"></canvas>
    </div>
    <div class="card">
        <div class="card-header"><h3 class="card-title"><i class="fas fa-star" style="color:#f59e0b;"></i> Top Active Users</h3></div>
        <canvas id="topUsersChart" height="180"></canvas>
    </div>
</div>

<!-- Real-time Online Devices -->
<div class="card">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
        <h3 class="card-title"><i class="fas fa-signal" style="color:var(--success);"></i> Live Device Status</h3>
        <span id="refresh-timer" style="font-size:0.8rem;color:var(--text-muted);">Auto-refreshing...</span>
    </div>
    <div id="live-devices-container">
        <div style="text-align:center;padding:20px;color:var(--text-muted);">Loading device status...</div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const chartDefaults = {
    plugins: { legend: { labels: { color: '#94a3b8', font: { size: 12 } } } },
    scales: {
        x: { ticks: { color: '#64748b' }, grid: { color: 'rgba(255,255,255,0.05)' } },
        y: { ticks: { color: '#64748b' }, grid: { color: 'rgba(255,255,255,0.05)' }, beginAtZero: true }
    }
};

// Message Volume Chart
new Chart(document.getElementById('msgChart'), {
    type: 'line',
    data: {
        labels: <?= $msgDays ?>,
        datasets: [{
            label: 'Messages',
            data: <?= $msgCounts ?>,
            borderColor: '#6366f1',
            backgroundColor: 'rgba(99,102,241,0.15)',
            tension: 0.4, fill: true, pointRadius: 4, pointBackgroundColor: '#6366f1'
        }]
    },
    options: { ...chartDefaults, responsive: true }
});

// Enrollment Status Donut
new Chart(document.getElementById('enrollChart'), {
    type: 'doughnut',
    data: {
        labels: <?= $enrollLabels ?>,
        datasets: [{
            data: <?= $enrollCounts ?>,
            backgroundColor: ['#22c55e','#6366f1','#f59e0b','#ef4444'],
            borderWidth: 2, borderColor: '#0a0e1a'
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom', labels: { color: '#94a3b8', padding: 16 } } }
    }
});

// Battery Distribution
new Chart(document.getElementById('batteryChart'), {
    type: 'bar',
    data: {
        labels: ['High (≥80%)', 'Medium (30-79%)', 'Low (<30%)', 'Unknown'],
        datasets: [{
            label: 'Devices',
            data: [<?= $batteryDist['high'] ?>, <?= $batteryDist['medium'] ?>, <?= $batteryDist['low'] ?>, <?= $batteryDist['unknown'] ?>],
            backgroundColor: ['#22c55e', '#f59e0b', '#ef4444', '#475569'],
            borderRadius: 6
        }]
    },
    options: { ...chartDefaults, responsive: true, plugins: { legend: { display: false } } }
});

// Top Users Bar
new Chart(document.getElementById('topUsersChart'), {
    type: 'bar',
    data: {
        labels: <?= $topNames ?>,
        datasets: [{
            label: 'Messages Sent',
            data: <?= $topCounts ?>,
            backgroundColor: 'rgba(99,102,241,0.7)',
            borderRadius: 6
        }]
    },
    options: { ...chartDefaults, responsive: true, indexAxis: 'y', plugins: { legend: { display: false } } }
});

// Real-time device status polling
function refreshDevices() {
    fetch('<?= APP_URL ?>/api/devices/list.php')
        .then(r => r.json())
        .then(data => {
            const devices = data.devices || [];
            const container = document.getElementById('live-devices-container');
            if (!devices.length) {
                container.innerHTML = '<div style="text-align:center;padding:24px;color:var(--text-muted);">No enrolled devices</div>';
                return;
            }
            let html = '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px;padding:16px;">';
            devices.forEach(d => {
                const online = d.is_online == 1;
                const battery = d.battery_level ?? '?';
                const battColor = battery >= 80 ? '#22c55e' : battery >= 30 ? '#f59e0b' : '#ef4444';
                html += `
                    <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,${online?'0.1':'0.04'});border-radius:10px;padding:14px;display:flex;gap:12px;align-items:center;">
                        <div style="width:40px;height:40px;border-radius:10px;background:rgba(99,102,241,0.15);display:flex;align-items:center;justify-content:center;">
                            <i class="fas fa-mobile-screen" style="color:#6366f1;"></i>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${d.device_name || d.model || d.imei}</div>
                            <div style="font-size:0.75rem;color:var(--text-muted);">${d.user_name || 'Unassigned'}</div>
                        </div>
                        <div style="text-align:right;">
                            <div style="width:8px;height:8px;border-radius:50%;background:${online?'#22c55e':'#475569'};margin-left:auto;margin-bottom:4px;${online?'box-shadow:0 0 6px #22c55e;':''}"></div>
                            <div style="font-size:0.75rem;color:${battColor};">🔋${battery}%</div>
                        </div>
                    </div>`;
            });
            html += '</div>';
            container.innerHTML = html;
        });
}

refreshDevices();
let countdown = 30;
setInterval(() => {
    countdown--;
    document.getElementById('refresh-timer').textContent = `Refreshing in ${countdown}s`;
    if (countdown <= 0) { countdown = 30; refreshDevices(); }
}, 1000);
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
