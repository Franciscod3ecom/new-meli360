<?php
// backend/api/bulk_update.php

// CORS Headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_start();

require_once __DIR__ . '/../config/database.php';

// Verificar sessão ativa
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

// Obter JSON input
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

// Fallback para $_POST se JSON falhar
if (!$input) {
    $input = $_POST;
}

$itemIds = $input['item_ids'] ?? [];
$action = $input['action'] ?? null;

if (empty($itemIds) || !in_array($action, ['paused', 'active'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Entrada inválida. Forneça item_ids e action (paused/active).']);
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
    $stmt = $pdo->prepare("SELECT access_token FROM accounts WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $account_id]);
    $accessToken = $stmt->fetchColumn();

    if (!$accessToken) {
        throw new Exception("Token de acesso não encontrado.");
    }

    // Helper: Atualizar no ML
    function updateMLStatus($itemId, $status, $token)
    {
        $ch = curl_init("https://api.mercadolibre.com/items/$itemId");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $token",
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['status' => $status]));
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['code' => $code, 'body' => json_decode($res, true)];
    }

    $results = ['success' => 0, 'failed' => 0, 'details' => []];

    foreach ($itemIds as $itemId) {
        $response = updateMLStatus($itemId, $action, $accessToken);

        if ($response['code'] === 200) {
            $results['success']++;

            // Atualizar DB local
            $updateStmt = $pdo->prepare("UPDATE items SET status = ?, updated_at = NOW() WHERE ml_id = ?");
            $updateStmt->execute([$action, $itemId]);
        } else {
            $results['failed']++;
            $results['details'][] = [
                'id' => $itemId,
                'error' => $response['body']['message'] ?? 'Erro no Mercado Livre'
            ];
        }

        usleep(100000); // 100ms
    }

    echo json_encode([
        'success' => true,
        'message' => "Processamento concluído",
        'data' => $results
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
