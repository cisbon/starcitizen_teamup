<?php
/**
 * Database Configuration
 * Star Citizen Team Up - PHP Backend
 */

// Suppress all errors in production (prevent HTML output before JSON)
error_reporting(0);
ini_set('display_errors', '0');

// Prevent direct access
if (!defined('API_ACCESS')) {
    http_response_code(403);
    die(json_encode(['error' => 'Direct access not permitted']));
}

// Database Configuration
// TODO: Update these with your actual database credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'starcitizen_teamup');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_CHARSET', 'utf8mb4');

// Security Settings
define('RATE_LIMIT_CREATE_GROUP', 3);      // Max attempts per minute
define('RATE_LIMIT_JOIN_GROUP', 10);       // Max attempts per minute
define('SESSION_TIMEOUT', 3600);            // 1 hour

// Database Connection
function getDbConnection() {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Database connection failed']);
            exit;
        }
    }

    return $pdo;
}

// Generate UUID v4 (compatible with PHP 5.x)
function generateUuid() {
    // Try to use random_bytes if available (PHP 7+)
    if (function_exists('random_bytes')) {
        $data = random_bytes(16);
    }
    // Fallback for PHP 5.x
    elseif (function_exists('openssl_random_pseudo_bytes')) {
        $data = openssl_random_pseudo_bytes(16);
    }
    // Last resort fallback
    else {
        $data = '';
        for ($i = 0; $i < 16; $i++) {
            $data .= chr(mt_rand(0, 255));
        }
    }

    // Set version to 0100
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    // Set bits 6-7 to 10
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

    // Output the 36 character UUID
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

// CORS Headers
function setCorsHeaders() {
    // Allow requests from any domain (public API)
    // Change this to your specific domain if you want to restrict access
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Accept, Origin, X-Requested-With');
    header('Access-Control-Max-Age: 86400');

    // Handle preflight OPTIONS request
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

// Security Headers
function setSecurityHeaders() {
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// Validation Functions
function validateHandle($handle) {
    if (!is_string($handle)) return false;
    $trimmed = trim($handle);
    if (strlen($trimmed) < 3 || strlen($trimmed) > 50) return false;
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $trimmed)) return false;
    return $trimmed;
}

function validateTitle($title) {
    if (!is_string($title)) return false;
    $trimmed = trim($title);
    if (strlen($trimmed) < 3 || strlen($trimmed) > 100) return false;
    return $trimmed;
}

function validateDescription($description) {
    if (empty($description)) return null;
    if (!is_string($description)) return false;
    if (strlen($description) > 500) return false;
    return trim($description);
}

function validateShip($ship) {
    if (empty($ship)) return null;
    if (!is_string($ship)) return false;
    $trimmed = trim($ship);
    if (strlen($trimmed) < 2 || strlen($trimmed) > 50) return false;
    return $trimmed;
}

function validateActivityType($activityType) {
    if (!is_string($activityType)) return false;
    $trimmed = trim($activityType);
    if (empty($trimmed)) return false;

    // Check against database
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM starcitizen_teamup_activity_types
        WHERE name = ? AND is_active = TRUE
    ");
    $stmt->execute([$trimmed]);
    $result = $stmt->fetch();

    return ($result['count'] > 0) ? $trimmed : false;
}

function validateMaxPlayers($maxPlayers) {
    $num = intval($maxPlayers);
    return ($num >= 2 && $num <= 50) ? $num : false;
}

function validateUuid($uuid) {
    return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid);
}

// Rate Limiting
function checkRateLimit($action, $identifier) {
    $pdo = getDbConnection();

    $limits = [
        'create_group' => RATE_LIMIT_CREATE_GROUP,
        'join_group' => RATE_LIMIT_JOIN_GROUP
    ];

    if (!isset($limits[$action])) return true;

    $maxAttempts = $limits[$action];
    $windowSeconds = 60;

    // Clean old attempts
    $stmt = $pdo->prepare("
        DELETE FROM rate_limits
        WHERE action = ? AND identifier = ? AND created_at < DATE_SUB(NOW(), INTERVAL ? SECOND)
    ");
    $stmt->execute([$action, $identifier, $windowSeconds]);

    // Count recent attempts
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM rate_limits
        WHERE action = ? AND identifier = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
    ");
    $stmt->execute([$action, $identifier, $windowSeconds]);
    $result = $stmt->fetch();

    if ($result['count'] >= $maxAttempts) {
        return false;
    }

    // Log this attempt
    $stmt = $pdo->prepare("INSERT INTO rate_limits (action, identifier) VALUES (?, ?)");
    $stmt->execute([$action, $identifier]);

    return true;
}

// Get client IP
function getClientIp() {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    // Sanitize IP
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

// Sanitize output
function sanitizeOutput($text) {
    return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
