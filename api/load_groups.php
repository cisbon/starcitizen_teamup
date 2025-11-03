<?php
/**
 * Load Groups API Endpoint
 * GET /api/load_groups.php
 */

define('API_ACCESS', true);
require_once __DIR__ . '/config.php';

setCorsHeaders();
setSecurityHeaders();

// Only allow GET (but also accept HEAD for testing)
$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
if ($method !== 'GET' && $method !== 'HEAD') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use GET.', 'received_method' => $method]);
    exit;
}

try {
    $pdo = getDbConnection();

    // First, check and close expired groups
    $pdo->exec("
        UPDATE starcitizen_teamup_groups
        SET status = 'closed'
        WHERE status IN ('open', 'full')
        AND expires_at < NOW()
    ");

    // Load all open groups
    $stmt = $pdo->prepare("
        SELECT
            g.*,
            COUNT(m.id) as member_count
        FROM starcitizen_teamup_groups g
        LEFT JOIN starcitizen_teamup_members m ON g.id = m.group_id
        WHERE g.status = 'open'
        GROUP BY g.id
        ORDER BY g.created_at DESC
        LIMIT 100
    ");

    $stmt->execute();
    $groups = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'groups' => $groups
    ]);

} catch (PDOException $e) {
    error_log('Database error in load_groups: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred']);
} catch (Exception $e) {
    error_log('Error in load_groups: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred']);
}
