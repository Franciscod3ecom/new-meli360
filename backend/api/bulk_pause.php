<?php
/**
 * Arquivo: bulk_pause.php
 * Descrição: Endpoint para pausar múltiplos anúncios no Mercado Livre
 */

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();

require_once __DIR__ . '/../config/database.php';

// Verificar sessão ativa
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

$ml_user_id = $_SESSION['user_id'];

// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Obter IDs dos itens
$itemIds = $_POST['item_ids'] ?? [];

if (empty($itemIds) || !is_array($itemIds)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nenhum item foi selecionado']);
    exit;
}

try {
    $pdo = getDatabaseConnection();

    // Determinar qual conta (account_id UUID) usar
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
        throw new Exception('Conta não selecionada ou não encontrada');
    }

    // Buscar tokens da conta
    $stmt = $pdo->prepare("SELECT access_token, ml_user_id FROM accounts WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $account_id]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$account) {
        throw new Exception('Dados da conta não encontrados');
    }

    $access_token = $account['access_token'];
    $ml_user_id = $account['ml_user_id'];

    // Função helper para pausar um item
    function pauseItem($itemId, $token)
    {
        $ch = curl_init("https://api.mercadolibre.com/items/$itemId");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $token",
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['status' => 'paused']));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['code' => $httpCode, 'body' => json_decode($response, true)];
    }

    // Processar cada item
    $results = [
        'success' => 0,
        'failed' => 0,
        'errors' => []
    ];

    foreach ($itemIds as $itemId) {
        $itemId = trim($itemId);
        if (empty($itemId))
            continue;

        $result = pauseItem($itemId, $access_token);

        if ($result['code'] === 200) {
            $results['success']++;

            // Atualizar status local no banco
            $updateStmt = $pdo->prepare("UPDATE items SET status = 'paused', updated_at = NOW() WHERE ml_id = :id");
            $updateStmt->execute([':id' => $itemId]);
        } else {
            $results['failed']++;
            $results['errors'][] = [
                'item_id' => $itemId,
                'error' => $result['body']['message'] ?? 'Erro desconhecido'
            ];
        }

        // Pequeno delay para não sobrecarregar a API
        usleep(100000); // 100ms
    }

    echo json_encode([
        'success' => true,
        'message' => "Operação concluída. Pausados: {$results['success']}, Falhas: {$results['failed']}",
        'data' => $results
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro no servidor: ' . $e->getMessage()
    ]);
}
?>