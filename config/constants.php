<?php
/**
 * Application Constants
 */

// App Info
define('APP_NAME', 'MDM Control Center');
define('APP_VERSION', '1.0.0');

// Auto-detect or read from .env
if (!function_exists('getConstantEnv')) {
    function getConstantEnv(string $key, string $default): string {
        static $env = null;
        if ($env === null) {
            $env = [];
            $envPath = __DIR__ . '/../.env';
            if (file_exists($envPath)) {
                $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (strpos(trim($line), '#') === 0) continue;
                    $parts = explode('=', $line, 2);
                    if (count($parts) === 2) {
                        $env[trim($parts[0])] = trim($parts[1]);
                    }
                }
            }
        }
        return $env[$key] ?? $default;
    }
}

$_protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$_host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_docRoot  = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$_currentDir = rtrim(str_replace('\\', '/', __DIR__), '/');
$_rootPhysicalPath = dirname($_currentDir);
$_basePath = '';
if (!empty($_docRoot) && strpos($_rootPhysicalPath, $_docRoot) === 0) {
    $_basePath = substr($_rootPhysicalPath, strlen($_docRoot));
}
$_basePath = rtrim(str_replace('\\', '/', $_basePath), '/');

$_defaultUrl = $_protocol . '://' . $_host . $_basePath;
define('APP_URL', getConstantEnv('APP_URL', $_defaultUrl));


// Session
define('SESSION_LIFETIME', 86400); // 24 hours
define('SESSION_NAME', 'mdm_session');

// File Uploads
define('UPLOAD_DIR', __DIR__ . '/../assets/uploads/');
define('APK_DIR', __DIR__ . '/../apk/');
define('MAX_UPLOAD_SIZE', 50 * 1024 * 1024); // 50MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('ALLOWED_FILE_TYPES', ['application/pdf', 'image/jpeg', 'image/png', 'application/msword']);

// Device Heartbeat
define('HEARTBEAT_INTERVAL', 10); // seconds
define('OFFLINE_THRESHOLD', 300); // 5 minutes without heartbeat = offline

// Enrollment
define('ENROLLMENT_TOKEN_EXPIRY', 86400); // 24 hours
define('ENROLLMENT_TOKEN_LENGTH', 32);

// Chat
define('WEBSOCKET_HOST', '0.0.0.0');
define('WEBSOCKET_PORT', 8080);
define('MAX_MESSAGE_LENGTH', 5000);
define('MESSAGES_PER_PAGE', 50);

// MDM Agent Package
define('MDM_AGENT_PACKAGE', 'com.mdm.agent');
define('CHAT_APP_PACKAGE', 'com.mdm.chat');

// API
define('API_KEY_HEADER', 'X-MDM-API-Key');
define('API_SECRET', 'mdm_secret_key_change_in_production');

// Pagination
define('ITEMS_PER_PAGE', 20);

// Firebase Cloud Messaging (V1 API — Service Account)
// Download your service account JSON from Firebase Console:
//   Project Settings → Service accounts → Generate new private key
// Save the file as: config/firebase-service-account.json
// No server key needed — FCM V1 uses OAuth2 from the service account.
define('FCM_SERVICE_ACCOUNT_PATH', __DIR__ . '/firebase-service-account.json');
