<?php
/**
 * Rate User API Endpoint
 * POST /api/rate_user.php
 * Allows rating a user (thumbs up/down) once per day
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

    // Validate player handle
    $playerHandle = validateHandle(isset($input['player_handle']) ? $input['player_handle'] : '');
    if ($playerHandle === false) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid player handle']);
        exit;
    }

    // Validate rating (1 for thumbs up, -1 for thumbs down)
    $rating = isset($input['rating']) ? intval($input['rating']) : 0;
    if ($rating !== 1 && $rating !== -1) {
        http_response_code(400);
        echo json_encode(['error' => 'Rating must be 1 (thumbs up) or -1 (thumbs down)']);
        exit;
    }

    // Get client IP
    $clientIp = getClientIp();

    $pdo = getDbConnection();

    // Check if this IP has already rated this user today
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT id FROM starcitizen_teamup_user_ratings
        WHERE rated_by_ip = ?
        AND player_handle = ?
        AND DATE(rated_at) = ?
    ");
    $stmt->execute([$clientIp, $playerHandle, $today]);

    if ($stmt->fetch()) {
        http_response_code(429);
        echo json_encode(['error' => 'You can only rate this user once per day']);
        exit;
    }

    // Insert rating
    $stmt = $pdo->prepare("
        INSERT INTO starcitizen_teamup_user_ratings (player_handle, rating, rated_by_ip)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$playerHandle, $rating, $clientIp]);

    echo json_encode([
        'success' => true,
        'message' => 'Rating submitted successfully'
    ]);

} catch (PDOException $e) {
    error_log('Database error in rate_user: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred']);
} catch (Exception $e) {
    error_log('Error in rate_user: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred']);
}
