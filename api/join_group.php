<?php
/**
 * Join Group API Endpoint
 * POST /api/join_group.php
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

    // Rate limiting
    $clientIp = getClientIp();
    if (!checkRateLimit('join_group', $clientIp)) {
        http_response_code(429);
        echo json_encode(['error' => 'Too many requests. Please wait a minute.']);
        exit;
    }

    // Validate group ID
    $groupId = isset($input['group_id']) ? $input['group_id'] : '';
    if (!validateUuid($groupId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid group ID']);
        exit;
    }

    // Validate player handle
    $playerHandle = validateHandle(isset($input['player_handle']) ? $input['player_handle'] : '');
    if ($playerHandle === false) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid handle. Must be 3-50 characters, letters, numbers, underscores and dashes only.']);
        exit;
    }

    $pdo = getDbConnection();

    // Start transaction
    $pdo->beginTransaction();

    try {
        // Check if group exists and is open
        $stmt = $pdo->prepare("
            SELECT status, max_players
            FROM starcitizen_teamup_groups
            WHERE id = ?
            FOR UPDATE
        ");
        $stmt->execute([$groupId]);
        $group = $stmt->fetch();

        if (!$group) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['error' => 'Group not found']);
            exit;
        }

        if ($group['status'] !== 'open') {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['error' => 'This group is no longer accepting members']);
            exit;
        }

        // Check current member count
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM starcitizen_teamup_members
            WHERE group_id = ?
        ");
        $stmt->execute([$groupId]);
        $memberCount = $stmt->fetch()['count'];

        if ($memberCount >= $group['max_players']) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['error' => 'This group is already full']);
            exit;
        }

        // Check if player is already in the group
        $stmt = $pdo->prepare("
            SELECT id FROM starcitizen_teamup_members
            WHERE group_id = ? AND player_handle = ?
        ");
        $stmt->execute([$groupId, $playerHandle]);

        if ($stmt->fetch()) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['error' => 'You are already in this group']);
            exit;
        }

        // Check 2-group limit (server-side validation)
        // Count groups created by this handle
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM starcitizen_teamup_groups
            WHERE creator_handle = ?
            AND status IN ('open', 'full')
        ");
        $stmt->execute([$playerHandle]);
        $createdCount = $stmt->fetch()['count'];

        // Count groups joined by this handle (as member, not creator)
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT m.group_id) as count
            FROM starcitizen_teamup_members m
            INNER JOIN starcitizen_teamup_groups g ON m.group_id = g.id
            WHERE m.player_handle = ?
            AND g.creator_handle != ?
            AND g.status IN ('open', 'full')
        ");
        $stmt->execute([$playerHandle, $playerHandle]);
        $joinedCount = $stmt->fetch()['count'];

        $totalGroups = $createdCount + $joinedCount;

        if ($totalGroups >= 2) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['error' => 'You can only be part of 2 groups maximum (created or joined). Please leave a group first.']);
            exit;
        }

        // Add player to group
        $stmt = $pdo->prepare("
            INSERT INTO starcitizen_teamup_members (group_id, player_handle)
            VALUES (?, ?)
        ");
        $stmt->execute([$groupId, $playerHandle]);

        // Check if group is now full
        $newMemberCount = $memberCount + 1;
        $isFull = ($newMemberCount >= $group['max_players']);

        if ($isFull) {
            // Mark group as full and extend expiry
            $newExpiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            $stmt = $pdo->prepare("
                UPDATE starcitizen_teamup_groups
                SET status = 'full', expires_at = ?
                WHERE id = ?
            ");
            $stmt->execute([$newExpiresAt, $groupId]);
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'is_full' => $isFull,
            'member_count' => $newMemberCount
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (PDOException $e) {
    error_log('Database error in join_group: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred']);
} catch (Exception $e) {
    error_log('Error in join_group: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred']);
}
