<?php
/**
 * Create Group API Endpoint
 * POST /api/create_group.php
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
    if (!checkRateLimit('create_group', $clientIp)) {
        http_response_code(429);
        echo json_encode(['error' => 'Too many requests. Please wait a minute.']);
        exit;
    }

    // Validate inputs
    $creatorHandle = validateHandle(isset($input['creator_handle']) ? $input['creator_handle'] : '');
    if ($creatorHandle === false) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid handle. Must be 3-50 characters, letters, numbers, underscores and dashes only.']);
        exit;
    }

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

    $pdo = getDbConnection();

    // Check 2-group limit (server-side validation)
    // Count ALL active groups where player is involved (either as creator or member)
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT g.id) as count
        FROM starcitizen_teamup_groups g
        LEFT JOIN starcitizen_teamup_members m ON g.id = m.group_id
        WHERE g.status IN ('open', 'full')
        AND (g.creator_handle = ? OR m.player_handle = ?)
    ");
    $stmt->execute([$creatorHandle, $creatorHandle]);
    $totalGroups = $stmt->fetch()['count'];

    if ($totalGroups >= 2) {
        http_response_code(400);
        echo json_encode([
            'error' => 'You can only be part of 2 groups maximum (created or joined). Please leave a group first.',
            'debug' => ['total_groups' => $totalGroups, 'handle' => $creatorHandle]
        ]);
        exit;
    }

    // Create group with UUID
    $groupId = generateUuid();
    // Non-full groups expire after 2 hours
    $expiresAt = date('Y-m-d H:i:s', strtotime('+2 hours'));

    $stmt = $pdo->prepare("
        INSERT INTO starcitizen_teamup_groups
        (id, creator_handle, activity_type, title, description, ship, discord_invite, max_players, status, expires_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'open', ?)
    ");

    $stmt->execute([
        $groupId,
        $creatorHandle,
        $activityType,
        $title,
        $description,
        $ship,
        $discordInvite,
        $maxPlayers,
        $expiresAt
    ]);

    // Add creator as first member
    $stmt = $pdo->prepare("
        INSERT INTO starcitizen_teamup_members (group_id, player_handle)
        VALUES (?, ?)
    ");
    $stmt->execute([$groupId, $creatorHandle]);

    // Return the created group
    $stmt = $pdo->prepare("
        SELECT * FROM starcitizen_teamup_groups WHERE id = ?
    ");
    $stmt->execute([$groupId]);
    $group = $stmt->fetch();

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'group' => $group
    ]);

} catch (PDOException $e) {
    error_log('Database error in create_group: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred']);
} catch (Exception $e) {
    error_log('Error in create_group: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred']);
}
