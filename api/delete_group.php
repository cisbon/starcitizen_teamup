<?php
/**
 * Delete Group API Endpoint
 * POST /api/delete_group.php
 * Allows group creator to delete the entire group
 */

define('API_ACCESS', true);
require_once __DIR__ . '/config.php';

setCorsHeaders();
setSecurityHeaders();

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use POST.']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }

    // Validate inputs
    $groupId = isset($input['group_id']) ? $input['group_id'] : '';
    $requestingUser = validateHandle(isset($input['requesting_user']) ? $input['requesting_user'] : '');

    if (!validateUuid($groupId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid group ID']);
        exit;
    }

    if ($requestingUser === false) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid requesting user']);
        exit;
    }

    $pdo = getDbConnection();

    // Get all members before deleting (for notification purposes)
    $stmt = $pdo->prepare("
        SELECT player_handle
        FROM starcitizen_teamup_members
        WHERE group_id = ?
    ");
    $stmt->execute([$groupId]);
    $members = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Verify requesting user is the group creator
    $stmt = $pdo->prepare("
        SELECT creator_handle, title
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

    if ($group['creator_handle'] !== $requestingUser) {
        http_response_code(403);
        echo json_encode(['error' => 'Only the group creator can delete the group']);
        exit;
    }

    // Delete the group (CASCADE will delete members automatically)
    $stmt = $pdo->prepare("
        DELETE FROM starcitizen_teamup_groups
        WHERE id = ?
    ");
    $stmt->execute([$groupId]);

    echo json_encode([
        'success' => true,
        'message' => 'Group deleted successfully',
        'group_title' => $group['title'],
        'affected_members' => array_filter($members, function($m) use ($requestingUser) {
            return $m !== $requestingUser;
        })
    ]);

} catch (PDOException $e) {
    error_log('Database error in delete_group: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred']);
} catch (Exception $e) {
    error_log('Error in delete_group: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred']);
}
