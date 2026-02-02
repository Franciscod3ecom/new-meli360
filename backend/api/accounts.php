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

    $user_type = $_SESSION['user_type'] ?? 'native';

    if ($user_type === 'oauth') {
        // OAuth login: only show the account itself
        $stmt = $pdo->prepare("SELECT id, ml_user_id, nickname, sync_status, updated_at FROM accounts WHERE ml_user_id = :ml_id");
        $stmt->execute([':ml_id' => $_SESSION['user_id']]);
    } else {
        // Native login: show accounts linked via user_accounts table
        $stmt = $pdo->prepare("
            SELECT a.id, a.ml_user_id, a.nickname, a.sync_status, a.updated_at 
            FROM accounts a
            INNER JOIN user_accounts ua ON a.id = ua.account_id
            WHERE ua.user_id = :user_id
            ORDER BY a.nickname
        ");
        $stmt->execute([':user_id' => $_SESSION['user_id']]);
    }

    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'current_user_id' => $_SESSION['user_id'],
        'accounts' => $accounts
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
