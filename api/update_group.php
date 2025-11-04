<?php
/**
 * Update Group API Endpoint
 * POST /api/update_group.php
 */

define('API_ACCESS', true);
require_once __DIR__ . '/config.php';

setCorsHeaders();
setSecurityHeaders();

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }

    // Validate group ID
    $groupId = isset($input['group_id']) ? $input['group_id'] : '';
    if (!validateUuid($groupId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid group ID']);
        exit;
    }

    // Validate creator handle (for authorization)
    $creatorHandle = validateHandle(isset($input['creator_handle']) ? $input['creator_handle'] : '');
    if ($creatorHandle === false) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid handle']);
        exit;
    }

    $pdo = getDbConnection();

    // Verify the group exists and the user is the creator
    $stmt = $pdo->prepare("
        SELECT id, creator_handle, status
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

    if ($group['creator_handle'] !== $creatorHandle) {
        http_response_code(403);
        echo json_encode(['error' => 'Only the group creator can update this group']);
        exit;
    }

    if ($group['status'] === 'closed') {
        http_response_code(400);
        echo json_encode(['error' => 'Cannot update a closed group']);
        exit;
    }

    // Validate inputs
    $activityType = validateActivityType(isset($input['activity_type']) ? $input['activity_type'] : '');
    if ($activityType === false) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid activity type.']);
        exit;
    }

    $title = validateTitle(isset($input['title']) ? $input['title'] : '');
    if ($title === false) {
        http_response_code(400);
        echo json_encode(['error' => 'Title must be between 3 and 100 characters.']);
        exit;
    }

    $description = validateDescription(isset($input['description']) ? $input['description'] : '');
    if ($description === false) {
        http_response_code(400);
        echo json_encode(['error' => 'Description must be 500 characters or less.']);
        exit;
    }

    $ship = validateShip(isset($input['ship']) ? $input['ship'] : '');
    if ($ship === false) {
        http_response_code(400);
        echo json_encode(['error' => 'Ship name must be between 2 and 50 characters if provided.']);
        exit;
    }

    // Validate Discord invite (optional)
    $discordInvite = isset($input['discord_invite']) ? trim($input['discord_invite']) : '';
    if ($discordInvite !== '' && strlen($discordInvite) > 255) {
        http_response_code(400);
        echo json_encode(['error' => 'Discord invite link must be 255 characters or less.']);
        exit;
    }
    if ($discordInvite === '') {
        $discordInvite = null;
    }

    $maxPlayers = validateMaxPlayers(isset($input['max_players']) ? $input['max_players'] : 0);
    if ($maxPlayers === false) {
        http_response_code(400);
        echo json_encode(['error' => 'Max players must be between 2 and 50.']);
        exit;
    }

    // Get current member count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM starcitizen_teamup_members
        WHERE group_id = ?
    ");
    $stmt->execute([$groupId]);
    $memberCount = $stmt->fetch()['count'];

    // Can't set max_players below current member count
    if ($maxPlayers < $memberCount) {
        http_response_code(400);
        echo json_encode([
            'error' => "Cannot set max players to {$maxPlayers}. You currently have {$memberCount} members."
        ]);
        exit;
    }

    // Update the group
    $stmt = $pdo->prepare("
        UPDATE starcitizen_teamup_groups
        SET activity_type = ?,
            title = ?,
            description = ?,
            ship = ?,
            discord_invite = ?,
            max_players = ?,
            status = CASE
                WHEN ? <= (SELECT COUNT(*) FROM starcitizen_teamup_members WHERE group_id = ?) THEN 'full'
                ELSE 'open'
            END,
            updated_at = NOW()
        WHERE id = ?
    ");

    $stmt->execute([
        $activityType,
        $title,
        $description,
        $ship,
        $discordInvite,
        $maxPlayers,
        $maxPlayers,
        $groupId,
        $groupId
    ]);

    // Return the updated group
    $stmt = $pdo->prepare("
        SELECT * FROM starcitizen_teamup_groups WHERE id = ?
    ");
    $stmt->execute([$groupId]);
    $updatedGroup = $stmt->fetch();

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'group' => $updatedGroup,
        'message' => 'Group updated successfully'
    ]);

} catch (PDOException $e) {
    error_log('Database error in update_group: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred']);
} catch (Exception $e) {
    error_log('Error in update_group: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred']);
}
