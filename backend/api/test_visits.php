<?php
/**
 * Debug script para testar a API de visitas do Mercado Livre
 * Acesse: https://d3ecom.com.br/meli360/backend/api/test_visits.php
 */

header('Content-Type: text/html; charset=utf-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

require_once __DIR__ . '/../config/database.php';

echo "<h1>🔍 Debug: API de Visitas</h1>";

if (!isset($_SESSION['user_id'])) {
    die("❌ Faça login primeiro.");
}

try {
    $pdo = getDatabaseConnection();

    // Resolver Conta
    $account = null;
    if (!empty($_SESSION['selected_account_id'])) {
        $stmt = $pdo->prepare("SELECT * FROM accounts WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $_SESSION['selected_account_id']]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
    } elseif (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'native') {
        $stmt = $pdo->prepare("
            SELECT a.* FROM accounts a
            JOIN user_accounts ua ON a.id = ua.account_id
            WHERE ua.user_id = :user_id
            ORDER BY a.nickname LIMIT 1
        ");
        $stmt->execute([':user_id' => $_SESSION['user_id']]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM accounts WHERE ml_user_id = :id LIMIT 1");
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$account) {
        die("❌ Nenhuma conta encontrada.");
    }

    echo "<p>✅ Conta: <strong>{$account['nickname']}</strong> (ML ID: {$account['ml_user_id']})</p>";

    $access_token = $account['access_token'];
    $ml_user_id = $account['ml_user_id'];

    // Buscar 3 itens
    echo "<h2>1. Buscando IDs de Itens...</h2>";
    $itemsResponse = file_get_contents("https://api.mercadolibre.com/users/$ml_user_id/items/search?status=active&limit=3", false, stream_context_create([
        'http' => [
            'header' => "Authorization: Bearer $access_token"
        ]
    ]));

    $itemsData = json_decode($itemsResponse, true);
    $itemIds = $itemsData['results'] ?? [];

    if (empty($itemIds)) {
        die("❌ Nenhum item ativo encontrado.");
    }

    echo "<p>✅ Encontrados: " . implode(', ', $itemIds) . "</p>";

    // Testar endpoint /visits/items
    echo "<h2>2. Testando /visits/items?ids=...</h2>";
    $idsString = implode(',', $itemIds);
    $url = "https://api.mercadolibre.com/visits/items?ids=$idsString";
    echo "<p><strong>URL:</strong> $url</p>";

    $visitsResponse = @file_get_contents($url, false, stream_context_create([
        'http' => [
            'header' => "Authorization: Bearer $access_token"
        ]
    ]));

    if (!$visitsResponse) {
        echo "<p style='color:red'>❌ ERRO: Não foi possível obter resposta da API</p>";
        echo "<p>HTTP Error: " . print_r(error_get_last(), true) . "</p>";
    } else {
        echo "<p style='color:green'>✅ Resposta recebida!</p>";
        echo "<h3>Resposta RAW:</h3>";
        echo "<pre style='background:#f0f0f0; padding:10px; border:1px solid #ccc'>";
        echo htmlspecialchars($visitsResponse);
        echo "</pre>";

        $visitsData = json_decode($visitsResponse, true);
        echo "<h3>Resposta JSON Decodificada:</h3>";
        echo "<pre style='background:#f0f0f0; padding:10px; border:1px solid #ccc'>";
        print_r($visitsData);
        echo "</pre>";

        echo "<h3>Mapeamento de Visitas:</h3>";
        $visitsMap = [];
        if (is_array($visitsData)) {
            foreach ($visitsData as $itemId => $visitCount) {
                $visitsMap[$itemId] = (int) $visitCount;
                echo "<p>Item <strong>$itemId</strong>: $visitCount visitas</p>";
            }
        }
    }

    // Testar detalhes de um item
    echo "<h2>3. Testando Detalhes do Primeiro Item</h2>";
    $firstItemId = $itemIds[0];
    $itemDetailUrl = "https://api.mercadolibre.com/items/$firstItemId";
    echo "<p><strong>URL:</strong> $itemDetailUrl</p>";

    $itemDetailResponse = file_get_contents($itemDetailUrl, false, stream_context_create([
        'http' => [
            'header' => "Authorization: Bearer $access_token"
        ]
    ]));

    $itemDetail = json_decode($itemDetailResponse, true);

    echo "<h3>Campos Importantes:</h3>";
    echo "<ul>";
    echo "<li><strong>title:</strong> " . ($itemDetail['title'] ?? 'N/A') . "</li>";
    echo "<li><strong>date_created:</strong> " . ($itemDetail['date_created'] ?? 'N/A') . "</li>";
    echo "<li><strong>status:</strong> " . ($itemDetail['status'] ?? 'N/A') . "</li>";
    echo "<li><strong>sold_quantity:</strong> " . ($itemDetail['sold_quantity'] ?? 'N/A') . "</li>";
    echo "</ul>";

    echo "<h3>Objeto Completo:</h3>";
    echo "<pre style='background:#f0f0f0; padding:10px; border:1px solid #ccc; max-height:400px; overflow:auto'>";
    print_r($itemDetail);
    echo "</pre>";

    // Verificar se está sendo salvo no banco
    echo "<h2>4. Verificando Banco de Dados</h2>";
    $stmt = $pdo->prepare("SELECT ml_id, title, date_created, total_visits FROM items WHERE ml_id = :id LIMIT 1");
    $stmt->execute([':id' => $firstItemId]);
    $dbItem = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($dbItem) {
        echo "<p style='color:green'>✅ Item encontrado no banco:</p>";
        echo "<pre style='background:#f0f0f0; padding:10px; border:1px solid #ccc'>";
        print_r($dbItem);
        echo "</pre>";
    } else {
        echo "<p style='color:orange'>⚠️ Item NÃO encontrado no banco (pode ainda não ter sido sincronizado)</p>";
    }

} catch (Exception $e) {
    echo "<p style='color:red'>❌ Erro: " . $e->getMessage() . "</p>";
}
