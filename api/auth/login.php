<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/rate-limit.php';

// Max 5 login attempts per IP per 60 seconds
rateLimit('login', maxRequests: 5, windowSeconds: 60);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$username = trim($input['username'] ?? '');
$password = $input['password'] ?? '';

if (!$username || !$password) {
    jsonResponse(['success' => false, 'message' => 'Username and password required'], 400);
}

$db = getDB();
$stmt = $db->prepare("SELECT * FROM admins WHERE (username = ? OR email = ?) AND is_active = 1");
$stmt->execute([$username, $username]);
$admin = $stmt->fetch();

if ($admin && password_verify($password, $admin['password_hash'])) {
    $_SESSION['admin_id'] = $admin['id'];
    $db->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?")->execute([$admin['id']]);
    jsonResponse(['success' => true, 'message' => 'Login successful', 'redirect' => APP_URL . '/admin/dashboard.php']);
} else {
    jsonResponse(['success' => false, 'message' => 'Invalid credentials'], 401);
}
