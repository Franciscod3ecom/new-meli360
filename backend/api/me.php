<?php
// backend/api/me.php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

// Check for valid session
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['authenticated' => false]);
    exit;
}

try {
    $pdo = getDatabaseConnection();
    
    // Optional: Validate if user still exists in DB or just trust session
    // For robustness, let's just return session data if available
    echo json_encode([
        'authenticated' => true, 
        'user' => [
            'ml_user_id' => $_SESSION['user_id'],
            'nickname' => $_SESSION['nickname'] ?? 'User'
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
