<?php
/**
 * Leave Group API Endpoint
 * POST /api/leave_group.php
 * Allows a member to leave a group they joined
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
    $playerHandle = validateHandle(isset($input['player_handle']) ? $input['player_handle'] : '');

    if (!validateUuid($groupId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid group ID']);
        exit;
    }

    if ($playerHandle === false) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid player handle']);
        exit;
    }

    $pdo = getDbConnection();

    // Get group info
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

    // Don't allow creator to leave (they should delete the group instead)
    if ($group['creator_handle'] === $playerHandle) {
        http_response_code(400);
        echo json_encode(['error' => 'Group creators cannot leave. Delete the group instead.']);
        exit;
    }

    // Remove the member
    $stmt = $pdo->prepare("
        DELETE FROM starcitizen_teamup_members
        WHERE group_id = ? AND player_handle = ?
    ");
    $stmt->execute([$groupId, $playerHandle]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'You are not a member of this group']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Left group successfully',
        'group_title' => $group['title'],
        'creator_handle' => $group['creator_handle']
    ]);

} catch (PDOException $e) {
    error_log('Database error in leave_group: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred']);
} catch (Exception $e) {
    error_log('Error in leave_group: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred']);
}
