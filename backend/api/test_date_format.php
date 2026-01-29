<?php
/**
 * Teste: verifica o formato exato do date_created retornado pela API
 */

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado']);
    exit;
}

try {
    $pdo = getDatabaseConnection();

    // Resolver conta
    $account_id = null;
    if (!empty($_SESSION['selected_account_id'])) {
        $account_id = $_SESSION['selected_account_id'];
    } elseif (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'native') {
        $stmt = $pdo->prepare("
            SELECT a.id FROM accounts a
            JOIN user_accounts ua ON a.id = ua.account_id
            WHERE ua.user_id = :user_id
            ORDER BY a.nickname LIMIT 1
        ");
        $stmt->execute([':user_id' => $_SESSION['user_id']]);
        $acc = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($acc)
            $account_id = $acc['id'];
    } else {
        $stmt = $pdo->prepare("SELECT id FROM accounts WHERE ml_user_id = :id LIMIT 1");
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $acc = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($acc)
            $account_id = $acc['id'];
    }

    if (!$account_id) {
        echo json_encode(['error' => 'Nenhuma conta encontrada']);
        exit;
    }

    // Buscar 5 itens para teste
    $stmt = $pdo->prepare("SELECT ml_id, title, date_created, total_visits, updated_at FROM items WHERE account_id = :account_id ORDER BY updated_at DESC LIMIT 5");
    $stmt->execute([':account_id' => $account_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'account_id' => $account_id,
        'items' => $items,
        'note' => 'Verifique o formato de date_created para cada item'
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
