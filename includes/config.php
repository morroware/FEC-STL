<?php
/**
 * Community 3D Model Vault - Configuration
 * Community-driven 3D Model Sharing Platform
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configuration
define('SITE_NAME', 'Community 3D Model Vault');
define('SITE_TAGLINE', 'Share. Print. Play.');
define('DATA_DIR', __DIR__ . '/../data/');
define('UPLOADS_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB

// Ensure directories exist
if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);
if (!is_dir(UPLOADS_DIR)) mkdir(UPLOADS_DIR, 0755, true);

/**
 * Helper Functions
 */

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function isAdmin(): bool {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

function getCurrentUser(): ?array {
    if (!isLoggedIn()) return null;
    require_once __DIR__ . '/db.php';
    return getUser($_SESSION['user_id']);
}

function redirect(string $url): void {
    header("Location: $url");
    exit;
}

function sanitize(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function generateId(): string {
    return bin2hex(random_bytes(8));
}

function timeAgo(string $datetime): string {
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    if ($diff < 2592000) return floor($diff / 604800) . 'w ago';
    return date('M j, Y', $time);
}

function formatFileSize(int $bytes): string {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' bytes';
}

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function csrfToken(): string {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Settings Helper - Get a site setting with fallback to default
 * Pass $reset = true to clear the cache (after saving settings)
 */
function setting(string $key, $default = null, bool $reset = false) {
    static $settings = null;
    if ($reset) {
        $settings = null;
        return null;
    }
    if ($settings === null) {
        require_once __DIR__ . '/db.php';
        $settings = getSettings();
    }
    return $settings[$key] ?? $default;
}

/**
 * Clear the settings cache (call after saving settings)
 */
function clearSettingsCache(): void {
    setting('', null, true);
}

/**
 * Check if site is in maintenance mode
 */
function isMaintenanceMode(): bool {
    return setting('maintenance_mode', false) === true;
}

/**
 * Get site name from settings
 */
function getSiteName(): string {
    return setting('site_name', SITE_NAME);
}

/**
 * Get max file size from settings (in bytes)
 */
function getMaxFileSize(): int {
    $mb = setting('max_file_size', 50);
    return $mb * 1024 * 1024;
}

/**
 * Get allowed file extensions from settings
 */
function getAllowedExtensions(): array {
    $extensions = setting('allowed_extensions', 'stl,obj');
    return array_map('trim', explode(',', strtolower($extensions)));
}

/**
 * Check if a feature is enabled
 */
function isFeatureEnabled(string $feature): bool {
    return setting('enable_' . $feature, true) === true;
}

/**
 * Check if current user needs approval
 */
function isCurrentUserApproved(): bool {
    if (!isLoggedIn()) return false;
    if (isAdmin()) return true;
    require_once __DIR__ . '/db.php';
    return isUserApproved($_SESSION['user_id']);
}
