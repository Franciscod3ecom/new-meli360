<?php
// backend/api/switch_account.php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

// Receive POST data
$data = json_decode(file_get_contents('php://input'), true);
$targetUserId = $data['target_user_id'] ?? null;

if (!$targetUserId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing target_user_id']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $pdo = getDatabaseConnection();
    
    // Verify if account exists
    $stmt = $pdo->prepare("SELECT ml_user_id, nickname, access_token FROM accounts WHERE ml_user_id = :id");
    $stmt->execute([':id' => $targetUserId]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($account) {
        // Update Session
        $_SESSION['user_id'] = $account['ml_user_id'];
        $_SESSION['nickname'] = $account['nickname'];
        $_SESSION['access_token'] = $account['access_token'];

        echo json_encode(['success' => true, 'new_user' => $account]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Account not found']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
