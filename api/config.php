<?php
/**
 * Database Configuration
 * Star Citizen Team Up - PHP Backend
 */

// Prevent direct access
if (!defined('API_ACCESS')) {
    http_response_code(403);
    die('Direct access not permitted');
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

// CORS Headers
function setCorsHeaders() {
    // Allow requests from your domain
    header('Access-Control-Allow-Origin: https://starcitizen.gamer.gd');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Max-Age: 86400');

    // Handle preflight OPTIONS request
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
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
    $validTypes = [
        'Bounty Hunting', 'Mining', 'Salvaging', 'Trading', 'Mercenary',
        'Investigations', 'Search and Rescue', 'Piracy', 'PVP',
        'Exploration', 'Xenothreat', 'Nine Tails Lockdown', 'Other'
    ];
    return in_array($activityType, $validTypes) ? $activityType : false;
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
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    // Sanitize IP
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

// Sanitize output
function sanitizeOutput($text) {
    return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
