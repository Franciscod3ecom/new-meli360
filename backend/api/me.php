<?php
// backend/api/me.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://d3ecom.com.br');
header('Access-Control-Allow-Credentials: true');

require_once __DIR__ . '/../config/database.php';

try {
    // Check for valid session
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['authenticated' => false, 'reason' => 'no_session']);
        exit;
    }

    $pdo = getDatabaseConnection();

    // Determine login type: native (saas_users table) or OAuth (accounts table)
    $user_type = $_SESSION['user_type'] ?? 'native';

    if ($user_type === 'oauth') {
        // OAuth login: lookup in accounts table
        $stmt = $pdo->prepare("SELECT id, ml_user_id, nickname FROM accounts WHERE ml_user_id = :ml_user_id LIMIT 1");
        $stmt->execute([':ml_user_id' => $_SESSION['user_id']]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$account) {
            echo json_encode(['authenticated' => false, 'reason' => 'oauth_account_not_found']);
            exit;
        }

        echo json_encode([
            'authenticated' => true,
            'user' => [
                'id' => $account['id'],
                'ml_user_id' => $account['ml_user_id'],
                'nickname' => $account['nickname']
            ]
        ]);
    } else {
        // Native login: lookup in saas_users table
        $stmt = $pdo->prepare("SELECT id, email FROM saas_users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            echo json_encode([
                'authenticated' => false,
                'reason' => 'user_not_found',
                'debug_id' => $_SESSION['user_id']
            ]);
            exit;
        }

        // Get all accounts linked to this user via user_accounts table
        $stmt = $pdo->prepare("
            SELECT a.id, a.ml_user_id, a.nickname 
            FROM accounts a
            INNER JOIN user_accounts ua ON a.id = ua.account_id
            WHERE ua.user_id = :user_id
            ORDER BY a.nickname
        ");
        $stmt->execute([':user_id' => $user['id']]);
        $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Determine selected account
        $selectedAccountId = $_SESSION['selected_account_id'] ?? ($accounts[0]['id'] ?? null);
        $selectedAccount = null;

        if ($selectedAccountId) {
            foreach ($accounts as $acc) {
                if ($acc['id'] === $selectedAccountId) {
                    $selectedAccount = $acc;
                    break;
                }
            }
        }

        // Fallback to first account if selected not found
        if (!$selectedAccount && !empty($accounts)) {
            $selectedAccount = $accounts[0];
        }

        echo json_encode([
            'authenticated' => true,
            'user' => [
                'id' => $selectedAccount['id'] ?? null,
                'email' => $user['email'],
                'user_id' => $user['id'],
                'nickname' => $selectedAccount['nickname'] ?? null,
                'ml_user_id' => $selectedAccount['ml_user_id'] ?? null,
                'accounts' => $accounts,
                'needs_account_link' => empty($accounts)
            ]
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'authenticated' => false,
        'error' => 'exception',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
} catch (Error $e) {
    http_response_code(500);
    echo json_encode([
        'authenticated' => false,
        'error' => 'fatal_error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
