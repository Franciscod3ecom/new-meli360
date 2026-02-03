<?php
// backend/api/delete_account.php
require_once __DIR__ . '/../config/database.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$accountId = $data['account_id'] ?? null;

if (!$accountId) {
    http_response_code(400);
    echo json_encode(['error' => 'Account ID is required']);
    exit;
}

try {
    $pdo = getDatabaseConnection();
    
    // Check if this account belongs to the user (security check)
    $stmtCheck = $pdo->prepare("SELECT 1 FROM user_accounts WHERE user_id = :user_id AND account_id = :account_id");
    $stmtCheck->execute([
        ':user_id' => $_SESSION['user_id'],
        ':account_id' => $accountId
    ]);
    
    if (!$stmtCheck->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'You do not have permission to delete this account']);
        exit;
    }

    // Hard Delete: Delete from 'accounts' table. 
    // ON DELETE CASCADE will wipe user_accounts and items automatically.
    $stmtDelete = $pdo->prepare("DELETE FROM accounts WHERE id = :account_id");
    $stmtDelete->execute([':account_id' => $accountId]);

    // If the deleted account was the one selected, clear it from session
    if (isset($_SESSION['selected_account_id']) && $_SESSION['selected_account_id'] == $accountId) {
        unset($_SESSION['selected_account_id']);
        unset($_SESSION['access_token']);
        unset($_SESSION['nickname']);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Conta e todos os dados associados foram excluídos com sucesso.'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
