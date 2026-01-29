<?php
/**
 * Debug ESPECÍFICO para os 3 itens que não sincronizam
 * Acesse: https://d3ecom.com.br/meli360/backend/api/debug_specific_items.php
 */

header('Content-Type: text/html; charset=utf-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

require_once __DIR__ . '/../config/database.php';

echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
    h1, h2, h3 { color: #333; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .info { color: blue; }
    pre { background: #fff; padding: 15px; border: 1px solid #ddd; border-radius: 5px; overflow: auto; max-height: 400px; }
    .section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
</style>";

echo "<h1>🔍 Debug de Itens Específicos</h1>";

if (!isset($_SESSION['user_id'])) {
    die("<p class='error'>❌ Faça login primeiro.</p>");
}

$specificIds = ['MLB4107433818', 'MLB3980693297', 'MLB5293435366'];

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
        die("<p class='error'>❌ Nenhuma conta encontrada.</p>");
    }

    $access_token = $account['access_token'];
    $ml_user_id = $account['ml_user_id'];
    $account_id = $account['id'];

    echo "<div class='section'>";
    echo "<h2>✅ Conta: {$account['nickname']}</h2>";
    echo "</div>";

    foreach ($specificIds as $itemId) {
        echo "<div class='section'>";
        echo "<h2>📦 Analisando: $itemId</h2>";

        // 1. Verificar se o item está na busca de ativos
        echo "<h3>1️⃣ Verificando se aparece na busca de ativos da API</h3>";
        $searchUrl = "https://api.mercadolibre.com/users/$ml_user_id/items/search?status=active&q=$itemId";
        $searchResponse = file_get_contents($searchUrl, false, stream_context_create([
            'http' => ['header' => "Authorization: Bearer $access_token"]
        ]));
        $searchData = json_decode($searchResponse, true);

        if (!empty($searchData['results']) && in_array($itemId, $searchData['results'])) {
            echo "<p class='success'>✅ Item ENCONTRADO na busca de ativos.</p>";
        } else {
            echo "<p class='error'>❌ Item NÃO encontrado na busca de ativos (status pode não ser 'active').</p>";

            // Tentar buscar sem filtro de status
            $searchUrlAll = "https://api.mercadolibre.com/users/$ml_user_id/items/search?q=$itemId";
            $searchResponseAll = file_get_contents($searchUrlAll, false, stream_context_create([
                'http' => ['header' => "Authorization: Bearer $access_token"]
            ]));
            $searchDataAll = json_decode($searchResponseAll, true);
            if (!empty($searchDataAll['results'])) {
                echo "<p class='info'>ℹ️ Item aparece na busca geral (sem filtro status).</p>";
            }
        }

        // 2. Detalhes diretos
        echo "<h3>2️⃣ Buscando detalhes diretos (/items/$itemId)</h3>";
        $itemUrl = "https://api.mercadolibre.com/items/$itemId";
        $itemResponse = @file_get_contents($itemUrl, false, stream_context_create([
            'http' => [
                'header' => "Authorization: Bearer $access_token",
                'ignore_errors' => true
            ]
        ]));

        if ($itemResponse) {
            $item = json_decode($itemResponse, true);
            if (isset($item['id'])) {
                echo "<p class='success'>✅ API retornou o item.</p>";
                echo "<ul>";
                echo "<li><strong>Título:</strong> {$item['title']}</li>";
                echo "<li><strong>Status:</strong> {$item['status']}</li>";
                echo "<li><strong>Data Criação:</strong> {$item['date_created']}</li>";
                echo "</ul>";
            } else {
                echo "<p class='error'>❌ Erro na API: " . htmlspecialchars($itemResponse) . "</p>";
            }
        } else {
            echo "<p class='error'>❌ Sem resposta da API para detalhes.</p>";
        }

        // 3. Verificar Banco
        echo "<h3>3️⃣ Verificando no Banco de Dados</h3>";
        $stmt = $pdo->prepare("SELECT * FROM items WHERE ml_id = :id");
        $stmt->execute([':id' => $itemId]);
        $dbItem = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($dbItem) {
            echo "<p class='success'>✅ Item presente no banco.</p>";
            echo "<pre>";
            print_r($dbItem);
            echo "</pre>";
        } else {
            echo "<p class='error'>❌ Item NÃO existe no banco de dados.</p>";
        }

        echo "</div>";
    }

} catch (Exception $e) {
    echo "<p class='error'>Error: " . $e->getMessage() . "</p>";
}
