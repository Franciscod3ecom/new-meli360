<?php
// backend/api/switch_account.php
// Endpoint to switch between ML accounts for native login users

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://d3ecom.com.br');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Read JSON input
$inputRaw = file_get_contents('php://input');
$input = json_decode($inputRaw, true);
$accountId = $input['account_id'] ?? '';

if (!$accountId) {
    http_response_code(400);
    // DEBUG: Return what we received to debug why it is empty
    echo json_encode(['success' => false, 'error' => 'account_id required. Received: ' . ($inputRaw ?: 'EMPTY BODY')]);
    exit;
}

try {
    $pdo = getDatabaseConnection();

    // Verify the account belongs to this user
    // FIXED: Handle both UUID (from accounts table) AND Numeric ID (ml_user_id from frontend)
    $userId = (int) $_SESSION['user_id'];

    if (is_numeric($accountId)) {
        // Input is numeric -> Search by ml_user_id
        $stmt = $pdo->prepare("
            SELECT a.id, a.ml_user_id, a.nickname
            FROM accounts a
            INNER JOIN user_accounts ua ON a.id = ua.account_id
            WHERE ua.user_id = :user_id AND a.ml_user_id = :account_id
            LIMIT 1
        ");
    } else {
        // Input is string/UUID -> Search by id
        $stmt = $pdo->prepare("
            SELECT a.id, a.ml_user_id, a.nickname
            FROM accounts a
            INNER JOIN user_accounts ua ON a.id = ua.account_id
            WHERE ua.user_id = :user_id AND a.id = :account_id
            LIMIT 1
        ");
    }

    $stmt->execute([
        ':user_id' => $userId,
        ':account_id' => $accountId
    ]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$account) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Account not found or not authorized']);
        exit;
    }

    // Update session with the REAL UUID from the database
    $_SESSION['selected_account_id'] = $account['id'];

    echo json_encode([
        'success' => true,
        'account' => [
            'id' => $account['id'],
            'nickname' => $account['nickname'],
            'ml_user_id' => $account['ml_user_id']
        ]
    ]);

} catch (Exception $e) {
    error_log("switch_account.php ERROR: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal Server Error: ' . $e->getMessage()]);
}
