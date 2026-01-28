<?php
// backend/api/me.php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://d3ecom.com.br');
header('Access-Control-Allow-Credentials: true');

require_once __DIR__ . '/../config/database.php';

// Check for valid session
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['authenticated' => false]);
    exit;
}

try {
    $pdo = getDatabaseConnection();

    // Determine login type: native (users table) or OAuth (accounts table)
    $user_type = $_SESSION['user_type'] ?? 'native';

    if ($user_type === 'oauth') {
        // OAuth login: lookup in accounts table
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
            echo json_encode(['authenticated' => false, 'debug' => 'User not found in saas_users']);
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

        // If no accounts linked, return authenticated but with no account selected
        if (empty($accounts)) {
            echo json_encode([
                'authenticated' => true,
                'user' => [
                    'id' => null,
                    'email' => $user['email'],
                    'user_id' => $user['id'],
                    'accounts' => [],
                    'needs_account_link' => true
                ]
            ]);
            exit;
        }

        // Use the first account as default (or from session if set)
        $selectedAccountId = $_SESSION['selected_account_id'] ?? $accounts[0]['id'];

        // Find the selected account in the list
        $selectedAccount = null;
        foreach ($accounts as $acc) {
            if ($acc['id'] === $selectedAccountId) {
                $selectedAccount = $acc;
                break;
            }
        }

        // Fallback to first account if selected not found
        if (!$selectedAccount) {
            $selectedAccount = $accounts[0];
        }

        echo json_encode([
            'authenticated' => true,
            'user' => [
                'id' => $selectedAccount['id'], // Current account UUID for filtering
                'email' => $user['email'],
                'user_id' => $user['id'],
                'nickname' => $selectedAccount['nickname'],
                'ml_user_id' => $selectedAccount['ml_user_id'],
                'accounts' => $accounts // All accounts for switcher
            ]
        ]);
    }

} catch (Exception $e) {
    // Log error for debugging
    error_log("me.php ERROR: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'authenticated' => false,
        'error' => 'Database error',
        'details' => $e->getMessage() // REMOVE in production!
    ]);
}
