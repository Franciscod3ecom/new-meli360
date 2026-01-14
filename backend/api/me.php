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
    
    // Get account_id from database
    $stmt = $pdo->prepare("SELECT id, ml_user_id, nickname FROM accounts WHERE ml_user_id = :ml_user_id LIMIT 1");
    $stmt->execute([':ml_user_id' => $_SESSION['user_id']]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$account) {
        echo json_encode(['authenticated' => false]);
        exit;
    }
    
    echo json_encode([
        'authenticated' => true, 
        'user' => [
            'id' => $account['id'], // UUID for filtering items
            'ml_user_id' => $account['ml_user_id'],
            'nickname' => $account['nickname']
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
