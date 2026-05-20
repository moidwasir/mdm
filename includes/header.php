<?php $pageTitle = $pageTitle ?? 'MDM Control Center'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($pageTitle) ?> - MDM Control Center</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
<div class="app-layout">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <header class="top-header">
        <div class="header-search">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Search devices, users..." id="global-search">
        </div>
        <div class="header-actions">
            <button class="header-btn" title="Notifications" id="btn-notifications">
                <i class="fas fa-bell"></i>
                <span class="notification-dot"></span>
            </button>
            <button class="header-btn" title="Refresh" onclick="location.reload()" id="btn-refresh">
                <i class="fas fa-arrows-rotate"></i>
            </button>
        </div>
    </header>
    <main class="main-content">
        <div class="toast-container" id="toast-container"></div>
