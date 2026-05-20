<?php
/**
 * Rate Limiter Middleware
 * Prevents brute-force attacks on APIs by tracking requests per IP
 *
 * Usage:
 *   require_once __DIR__ . '/../includes/rate-limit.php';
 *   rateLimit('login', maxRequests: 5, windowSeconds: 60);
 */

function rateLimit(string $action, int $maxRequests = 10, int $windowSeconds = 60): void
{
    $ip      = $_SERVER['HTTP_CF_CONNECTING_IP']   // Cloudflare real IP
           ?? $_SERVER['HTTP_X_FORWARDED_FOR']     // Reverse proxy
           ?? $_SERVER['REMOTE_ADDR']
           ?? '0.0.0.0';

    // Use APCu if available (fast, in-memory), otherwise file-based
    if (function_exists('apcu_fetch')) {
        _rateLimitApcu($ip, $action, $maxRequests, $windowSeconds);
    } else {
        _rateLimitFile($ip, $action, $maxRequests, $windowSeconds);
    }
}

function _rateLimitApcu(string $ip, string $action, int $max, int $window): void
{
    $key   = "rl:{$action}:{$ip}";
    $count = apcu_fetch($key, $success);

    if (!$success) {
        apcu_store($key, 1, $window);
        return;
    }

    if ($count >= $max) {
        header('Content-Type: application/json');
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'Too many requests. Please wait and try again.', 'retry_after' => $window]);
        exit;
    }

    apcu_inc($key);
}

function _rateLimitFile(string $ip, string $action, int $max, int $window): void
{
    $dir  = sys_get_temp_dir() . '/mdm_rl/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $key  = md5("{$action}_{$ip}");
    $file = $dir . $key . '.json';
    $now  = time();

    $data = ['requests' => [], 'count' => 0];
    if (file_exists($file)) {
        $raw = @json_decode(file_get_contents($file), true);
        if ($raw) $data = $raw;
    }

    // Prune old entries outside the window
    $data['requests'] = array_filter($data['requests'], fn($ts) => $ts > ($now - $window));
    $data['requests'][] = $now;
    $data['count'] = count($data['requests']);

    file_put_contents($file, json_encode($data), LOCK_EX);

    if ($data['count'] > $max) {
        header('Content-Type: application/json');
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'Too many requests. Please wait and try again.', 'retry_after' => $window]);
        exit;
    }
}
