<?php
/**
 * Get User Rating API Endpoint
 * GET /api/get_user_rating.php?handle=username
 * Returns user's rating score and whether current IP can rate them
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
    // Validate player handle
    $playerHandle = validateHandle(isset($_GET['handle']) ? $_GET['handle'] : '');
    if ($playerHandle === false) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid player handle']);
        exit;
    }

    $pdo = getDbConnection();
    $clientIp = getClientIp();

    // Get total rating score (sum of all ratings)
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(rating), 0) as total_score,
            COUNT(*) as rating_count
        FROM starcitizen_teamup_user_ratings
        WHERE player_handle = ?
    ");
    $stmt->execute([$playerHandle]);
    $ratingData = $stmt->fetch();

    // Check if current IP has already rated this user
    // If they have, they can update their rating (can_rate will be false to hide buttons)
    $stmt = $pdo->prepare("
        SELECT rating FROM starcitizen_teamup_user_ratings
        WHERE rated_by_ip = ?
        AND player_handle = ?
    ");
    $stmt->execute([$clientIp, $playerHandle]);
    $existingRating = $stmt->fetch();
    $canRate = !$existingRating; // Can only rate if never rated before

    echo json_encode([
        'success' => true,
        'player_handle' => $playerHandle,
        'total_score' => intval($ratingData['total_score']),
        'rating_count' => intval($ratingData['rating_count']),
        'can_rate' => $canRate
    ]);

} catch (PDOException $e) {
    error_log('Database error in get_user_rating: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred']);
} catch (Exception $e) {
    error_log('Error in get_user_rating: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred']);
}
