<?php
/**
 * FCM Push Notification Sender — Firebase Cloud Messaging V1 API
 *
 * Uses Service Account JWT authentication (Legacy server keys are deprecated).
 * Place your service account JSON at: config/firebase-service-account.json
 */

require_once __DIR__ . '/../../config/constants.php';

// ── OAuth2 Access Token via Service Account JWT ───────────────────────────────

function getFcmAccessToken(): ?string
{
    static $cachedToken = null;
    static $tokenExpiry = 0;

    // Return cached token if still valid (with 60s buffer)
    if ($cachedToken && time() < $tokenExpiry - 60) {
        return $cachedToken;
    }

    $serviceAccountPath = __DIR__ . '/../../config/firebase-service-account.json';
    if (!file_exists($serviceAccountPath)) {
        error_log('[FCM] Service account file not found: ' . $serviceAccountPath);
        return null;
    }

    $sa = json_decode(file_get_contents($serviceAccountPath), true);
    if (!$sa || !isset($sa['private_key'], $sa['client_email'])) {
        error_log('[FCM] Invalid service account JSON');
        return null;
    }

    // Build JWT header + claims
    $now = time();
    $header = base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $claims = base64UrlEncode(json_encode([
        'iss'   => $sa['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud'   => 'https://oauth2.googleapis.com/token',
        'iat'   => $now,
        'exp'   => $now + 3600,
    ]));

    // Sign with RS256 using the private key
    $signingInput = $header . '.' . $claims;
    $privateKey   = openssl_pkey_get_private($sa['private_key']);
    if (!$privateKey) {
        error_log('[FCM] Failed to load private key');
        return null;
    }

    $signature = '';
    if (!openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
        error_log('[FCM] Failed to sign JWT');
        return null;
    }

    $jwt = $signingInput . '.' . base64UrlEncode($signature);

    // Exchange JWT for access token
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    if (!isset($data['access_token'])) {
        error_log('[FCM] Token exchange failed: ' . $response);
        return null;
    }

    $cachedToken = $data['access_token'];
    $tokenExpiry = $now + ($data['expires_in'] ?? 3600);
    return $cachedToken;
}

function base64UrlEncode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

// ── FCM V1 API Sender ─────────────────────────────────────────────────────────

/**
 * Send a push notification to a list of FCM tokens using the V1 API.
 * V1 sends to ONE token at a time (no batch like legacy), so we loop.
 */
function sendFcmNotification(array $tokens, string $title, string $body, array $data = []): bool
{
    if (empty($tokens)) return false;

    $serviceAccountPath = __DIR__ . '/../../config/firebase-service-account.json';
    if (!file_exists($serviceAccountPath)) {
        error_log('[FCM] Service account not configured');
        return false;
    }

    $sa        = json_decode(file_get_contents($serviceAccountPath), true);
    $projectId = $sa['project_id'] ?? null;

    if (!$projectId) {
        error_log('[FCM] project_id missing from service account JSON');
        return false;
    }

    $accessToken = getFcmAccessToken();
    if (!$accessToken) {
        error_log('[FCM] Could not get access token');
        return false;
    }

    $endpoint = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
    $success  = 0;

    foreach ($tokens as $token) {
        $payload = json_encode([
            'message' => [
                'token'        => $token,
                'notification' => ['title' => $title, 'body' => $body],
                'data'         => array_map('strval', $data),
                'android'      => [
                    'priority'     => 'high',
                    'notification' => ['sound' => 'default', 'click_action' => 'FLUTTER_NOTIFICATION_CLICK'],
                ],
            ],
        ]);

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $success++;
        } else {
            error_log("[FCM] Failed for token {$token}: HTTP {$httpCode} — {$response}");
        }
    }

    error_log("[FCM] Sent to {$success}/" . count($tokens) . " devices");
    return $success > 0;
}

// ── Convenience wrappers ──────────────────────────────────────────────────────

function notifyConversationMembers(int $convId, int $senderUserId, string $senderName, string $messageContent): void
{
    require_once __DIR__ . '/../../config/database.php';
    $db = getDB();

    $stmt = $db->prepare("
        SELECT u.fcm_token FROM conversation_members cm
        JOIN users u ON u.id = cm.user_id
        WHERE cm.conversation_id = ?
          AND cm.user_id != ?
          AND u.fcm_token IS NOT NULL AND u.fcm_token != ''
          AND u.is_active = 1
    ");
    $stmt->execute([$convId, $senderUserId]);
    $tokens = array_column($stmt->fetchAll(), 'fcm_token');
    if (empty($tokens)) return;

    $preview = mb_strlen($messageContent) > 100 ? mb_substr($messageContent, 0, 97) . '...' : $messageContent;

    sendFcmNotification($tokens, $senderName, $preview, [
        'type'            => 'new_message',
        'conversation_id' => (string)$convId,
    ]);
}

function sendAdminAlert(int $userId, string $title, string $body): void
{
    require_once __DIR__ . '/../../config/database.php';
    $db   = getDB();
    $stmt = $db->prepare("SELECT fcm_token FROM users WHERE id = ? AND fcm_token IS NOT NULL");
    $stmt->execute([$userId]);
    $row  = $stmt->fetch();
    if ($row && $row['fcm_token']) {
        sendFcmNotification([$row['fcm_token']], $title, $body, ['type' => 'admin_alert']);
    }
}

// ── Direct HTTP trigger from admin panel ──────────────────────────────────────
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    require_once __DIR__ . '/../../includes/functions.php';
    header('Content-Type: application/json');
    if ($_POST['action'] === 'send_alert') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $title  = sanitize($_POST['title'] ?? '');
        $body   = sanitize($_POST['body']  ?? '');
        if ($userId && $title) {
            sendAdminAlert($userId, $title, $body);
            jsonResponse(['success' => true, 'message' => 'Alert sent']);
        }
        jsonResponse(['success' => false, 'message' => 'Missing fields'], 400);
    }
}
