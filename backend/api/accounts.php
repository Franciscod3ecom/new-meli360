<?php
// backend/api/accounts.php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

// Protect Endpoint
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $pdo = getDatabaseConnection();
    
    // Fetch all accounts
    $stmt = $pdo->query("SELECT ml_user_id, nickname, sync_status, updated_at FROM accounts ORDER BY updated_at DESC");
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'current_user_id' => $_SESSION['user_id'],
        'accounts' => $accounts
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
