<?php
/**
 * Rate User API Endpoint
 * POST /api/rate_user.php
 * Allows rating a user (thumbs up/down)
 * Each IP can only rate each player once (but can update their rating)
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

    // Use INSERT ... ON DUPLICATE KEY UPDATE to either insert or update
    // This prevents the table from growing indefinitely
    // Each (rated_by_ip, player_handle) pair will have only ONE entry
    $stmt = $pdo->prepare("
        INSERT INTO starcitizen_teamup_user_ratings (player_handle, rating, rated_by_ip, rated_at)
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            rating = VALUES(rating),
            rated_at = NOW()
    ");
    $stmt->execute([$playerHandle, $rating, $clientIp]);

    // Check if this was an insert or update
    $wasUpdate = $pdo->lastInsertId() == 0;

    echo json_encode([
        'success' => true,
        'message' => $wasUpdate ? 'Rating updated successfully' : 'Rating submitted successfully',
        'action' => $wasUpdate ? 'updated' : 'created'
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
