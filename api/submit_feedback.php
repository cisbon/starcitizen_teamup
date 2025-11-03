<?php
/**
 * Submit Feedback API Endpoint
 * POST /api/submit_feedback.php
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

    // Validate rating
    $rating = isset($input['rating']) ? intval($input['rating']) : 0;
    if ($rating < 1 || $rating > 5) {
        http_response_code(400);
        echo json_encode(['error' => 'Rating must be between 1 and 5']);
        exit;
    }

    // Validate message (optional)
    $message = isset($input['message']) ? trim($input['message']) : null;
    if ($message !== null && strlen($message) > 1000) {
        http_response_code(400);
        echo json_encode(['error' => 'Message must be 1000 characters or less']);
        exit;
    }

    // Get client IP
    $clientIp = getClientIp();

    $pdo = getDbConnection();

    // Insert feedback
    $stmt = $pdo->prepare("
        INSERT INTO starcitizen_teamup_feedback (rating, message, user_ip)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$rating, $message, $clientIp]);

    echo json_encode([
        'success' => true,
        'message' => 'Thank you for your feedback!'
    ]);

} catch (PDOException $e) {
    error_log('Database error in submit_feedback: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred']);
} catch (Exception $e) {
    error_log('Error in submit_feedback: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred']);
}
