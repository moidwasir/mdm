<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['admin_id'])) {
    if (isAjax()) {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }
    redirect(APP_URL . '/index.php', 'Please login to continue', 'error');
}

$currentAdmin = getCurrentAdmin();
if (!$currentAdmin) {
    session_destroy();
    redirect(APP_URL . '/index.php', 'Session expired', 'error');
}
