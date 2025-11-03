<?php
/**
 * Get Group Members API Endpoint
 * GET /api/get_group_members.php?group_id=xxx
 */

define('API_ACCESS', true);
require_once __DIR__ . '/config.php';

setCorsHeaders();
setSecurityHeaders();

// Only allow GET
$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
if ($method !== 'GET' && $method !== 'HEAD') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use GET.']);
    exit;
}

try {
    // Get group ID from query string
    $groupId = isset($_GET['group_id']) ? $_GET['group_id'] : '';

    if (!validateUuid($groupId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid group ID']);
        exit;
    }

    $pdo = getDbConnection();

    // Get group info
    $stmt = $pdo->prepare("
        SELECT id, creator_handle, title, activity_type, ship, max_players, status, created_at
        FROM starcitizen_teamup_groups
        WHERE id = ?
    ");
    $stmt->execute([$groupId]);
    $group = $stmt->fetch();

    if (!$group) {
        http_response_code(404);
        echo json_encode(['error' => 'Group not found']);
        exit;
    }

    // Get all members
    $stmt = $pdo->prepare("
        SELECT player_handle, joined_at
        FROM starcitizen_teamup_members
        WHERE group_id = ?
        ORDER BY joined_at ASC
    ");
    $stmt->execute([$groupId]);
    $members = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'group' => $group,
        'members' => $members,
        'member_count' => count($members)
    ]);

} catch (PDOException $e) {
    error_log('Database error in get_group_members: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred']);
} catch (Exception $e) {
    error_log('Error in get_group_members: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred']);
}
