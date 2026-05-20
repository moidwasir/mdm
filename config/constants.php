<?php
/**
 * Application Constants
 */

// App Info
define('APP_NAME', 'MDM Control Center');
define('APP_VERSION', '1.0.0');

// Auto-detect APP_URL based on environment
$_protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$_host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_basePath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/mdm')), '/');
define('APP_URL', $_protocol . '://' . $_host . '/mdm');

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
define('HEARTBEAT_INTERVAL', 60); // seconds
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
