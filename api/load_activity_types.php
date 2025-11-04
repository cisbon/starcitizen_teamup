<?php
/**
 * Load Activity Types
 * Returns all active activity types from the database
 */

define('API_ACCESS', true);
require_once __DIR__ . '/config.php';

setCorsHeaders();
setSecurityHeaders();

// Only allow GET requests (but also accept HEAD for testing)
$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
if ($method !== 'GET' && $method !== 'HEAD') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $pdo = getDbConnection();

    // Fetch all active activity types ordered by display_order
    $stmt = $pdo->prepare("
        SELECT id, name, display_order
        FROM starcitizen_teamup_activity_types
        WHERE is_active = TRUE
        ORDER BY display_order ASC, name ASC
    ");
    $stmt->execute();

    $activityTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'activity_types' => $activityTypes
    ]);

} catch (PDOException $e) {
    error_log('Database error in load_activity_types.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred'
    ]);
}
