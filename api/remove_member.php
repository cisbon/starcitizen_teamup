<?php
/**
 * Remove Member API Endpoint
 * POST /api/remove_member.php
 * Allows group creator to remove a specific member
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
    $requestingUser = validateHandle(isset($input['requesting_user']) ? $input['requesting_user'] : '');

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

    if ($requestingUser === false) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid requesting user']);
        exit;
    }

    $pdo = getDbConnection();

    // Verify requesting user is the group creator
    $stmt = $pdo->prepare("
        SELECT creator_handle
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
        echo json_encode(['error' => 'Only the group creator can remove members']);
        exit;
    }

    // Don't allow removing the creator
    if ($playerHandle === $requestingUser) {
        http_response_code(400);
        echo json_encode(['error' => 'Cannot remove yourself. Use delete group instead.']);
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
        echo json_encode(['error' => 'Member not found in group']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Member removed successfully',
        'removed_handle' => $playerHandle
    ]);

} catch (PDOException $e) {
    error_log('Database error in remove_member: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred']);
} catch (Exception $e) {
    error_log('Error in remove_member: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred']);
}
