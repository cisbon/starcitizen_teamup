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

    $maxPlayers = validateMaxPlayers(isset($input['max_players']) ? $input['max_players'] : 0);
    if ($maxPlayers === false) {
        http_response_code(400);
        echo json_encode(['error' => 'Max players must be between 2 and 50.']);
        exit;
    }

    $pdo = getDbConnection();

    // Check 2-group limit (server-side validation)
    // Count groups created by this handle
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM starcitizen_teamup_groups
        WHERE creator_handle = ?
        AND status IN ('open', 'full')
    ");
    $stmt->execute([$creatorHandle]);
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
    $stmt->execute([$creatorHandle, $creatorHandle]);
    $joinedCount = $stmt->fetch()['count'];

    $totalGroups = $createdCount + $joinedCount;

    if ($totalGroups >= 2) {
        http_response_code(400);
        echo json_encode(['error' => 'You can only be part of 2 groups maximum (created or joined). Please leave a group first.']);
        exit;
    }

    // Create group with UUID
    $groupId = generateUuid();
    // Non-full groups expire after 2 hours
    $expiresAt = date('Y-m-d H:i:s', strtotime('+2 hours'));

    $stmt = $pdo->prepare("
        INSERT INTO starcitizen_teamup_groups
        (id, creator_handle, activity_type, title, description, ship, max_players, status, expires_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'open', ?)
    ");

    $stmt->execute([
        $groupId,
        $creatorHandle,
        $activityType,
        $title,
        $description,
        $ship,
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
