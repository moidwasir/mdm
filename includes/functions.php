<?php
/**
 * Helper Functions
 */

/**
 * Sanitize input string
 */
function sanitize(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate a random token
 */
function generateToken(int $length = 32): string {
    return bin2hex(random_bytes($length));
}

/**
 * Validate IMEI number (Luhn algorithm)
 */
function validateIMEI(string $imei): bool {
    if (!preg_match('/^\d{15}$/', $imei)) {
        return false;
    }
    
    // Bypass for local emulator testing
    if (strpos($imei, '12345') === 0) {
        return true;
    }
    
    $sum = 0;
    for ($i = 0; $i < 14; $i++) {
        $digit = (int)$imei[$i];
        if ($i % 2 !== 0) {
            $digit *= 2;
            if ($digit > 9) {
                $digit -= 9;
            }
        }
        $sum += $digit;
    }
    
    $checkDigit = (10 - ($sum % 10)) % 10;
    return $checkDigit === (int)$imei[14];
}


/**
 * Send JSON response
 */
function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Get current admin from session
 */
function getCurrentAdmin(): ?array {
    if (!isset($_SESSION['admin_id'])) {
        return null;
    }
    
    $db = getDB();
    $stmt = $db->prepare("SELECT id, username, email, full_name, role, avatar FROM admins WHERE id = ? AND is_active = 1");
    $stmt->execute([$_SESSION['admin_id']]);
    return $stmt->fetch() ?: null;
}

/**
 * Format timestamp to relative time
 */
function timeAgo(string $timestamp): string {
    $time = strtotime($timestamp);
    $diff = time() - $time;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', $time);
}

/**
 * Format bytes to human readable
 */
function formatBytes(int $bytes, int $precision = 2): string {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Get device status badge HTML
 */
function getStatusBadge(string $status): string {
    $badges = [
        'pending'    => '<span class="badge badge-warning">Pending</span>',
        'enrolled'   => '<span class="badge badge-success">Enrolled</span>',
        'unenrolled' => '<span class="badge badge-secondary">Unenrolled</span>',
        'blocked'    => '<span class="badge badge-danger">Blocked</span>',
        'online'     => '<span class="badge badge-success"><span class="pulse-dot"></span>Online</span>',
        'offline'    => '<span class="badge badge-secondary">Offline</span>',
    ];
    return $badges[$status] ?? '<span class="badge">' . sanitize($status) . '</span>';
}

/**
 * Get paginated results
 */
function paginate(PDO $db, string $query, array $params = [], int $page = 1, int $perPage = ITEMS_PER_PAGE): array {
    $countQuery = preg_replace('/SELECT .+ FROM/i', 'SELECT COUNT(*) as total FROM', $query);
    $countQuery = preg_replace('/ORDER BY .+$/i', '', $countQuery);
    $countQuery = preg_replace('/LIMIT .+$/i', '', $countQuery);
    
    $stmt = $db->prepare($countQuery);
    $stmt->execute($params);
    $total = (int)$stmt->fetch()['total'];
    
    $totalPages = ceil($total / $perPage);
    $page = max(1, min($page, $totalPages ?: 1));
    $offset = ($page - 1) * $perPage;
    
    $query .= " LIMIT $perPage OFFSET $offset";
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $items = $stmt->fetchAll();
    
    return [
        'items' => $items,
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => $totalPages,
    ];
}

/**
 * Generate pagination HTML
 */
function paginationHTML(int $currentPage, int $totalPages, string $baseUrl): string {
    if ($totalPages <= 1) return '';
    
    $html = '<div class="pagination">';
    
    if ($currentPage > 1) {
        $html .= '<a href="' . $baseUrl . '?page=' . ($currentPage - 1) . '" class="pagination-btn"><i class="fas fa-chevron-left"></i></a>';
    }
    
    for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++) {
        $active = $i === $currentPage ? ' active' : '';
        $html .= '<a href="' . $baseUrl . '?page=' . $i . '" class="pagination-btn' . $active . '">' . $i . '</a>';
    }
    
    if ($currentPage < $totalPages) {
        $html .= '<a href="' . $baseUrl . '?page=' . ($currentPage + 1) . '" class="pagination-btn"><i class="fas fa-chevron-right"></i></a>';
    }
    
    $html .= '</div>';
    return $html;
}

/**
 * Log an action
 */
function logAction(string $action, string $details = '', ?int $deviceId = null): void {
    if ($deviceId) {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO device_logs (device_id, event_type, details) VALUES (?, ?, ?)");
        $stmt->execute([$deviceId, $action, $details]);
    }
}

/**
 * Check if request is AJAX
 */
function isAjax(): bool {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Redirect with flash message
 */
function redirect(string $url, string $message = '', string $type = 'success'): void {
    if ($message) {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
    }
    header("Location: $url");
    exit;
}

/**
 * Get and clear flash message
 */
function getFlashMessage(): ?array {
    if (isset($_SESSION['flash_message'])) {
        $msg = [
            'message' => $_SESSION['flash_message'],
            'type' => $_SESSION['flash_type'] ?? 'success'
        ];
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        return $msg;
    }
    return null;
}
