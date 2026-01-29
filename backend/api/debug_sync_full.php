<?php
/**
 * Debug COMPLETO: mostra EXATAMENTE o que está sendo salvo no banco
 * Acesse: https://d3ecom.com.br/meli360/backend/api/debug_sync_full.php
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

echo "<h1>🔍 Debug Completo do Sync</h1>";

if (!isset($_SESSION['user_id'])) {
    die("<p class='error'>❌ Faça login primeiro.</p>");
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
        die("<p class='error'>❌ Nenhuma conta encontrada.</p>");
    }

    echo "<div class='section'>";
    echo "<h2>✅ Conta Identificada</h2>";
    echo "<p><strong>Nickname:</strong> {$account['nickname']}</p>";
    echo "<p><strong>ML User ID:</strong> {$account['ml_user_id']}</p>";
    echo "<p><strong>Account ID:</strong> {$account['id']}</p>";
    echo "</div>";

    $access_token = $account['access_token'];
    $ml_user_id = $account['ml_user_id'];
    $account_id = $account['id'];

    // Buscar APENAS 2 itens para teste
    echo "<div class='section'>";
    echo "<h2>1️⃣ Buscando IDs de Itens...</h2>";
    $itemsResponse = file_get_contents("https://api.mercadolibre.com/users/$ml_user_id/items/search?status=active&limit=2", false, stream_context_create([
        'http' => [
            'header' => "Authorization: Bearer $access_token"
        ]
    ]));

    $itemsData = json_decode($itemsResponse, true);
    $itemIds = $itemsData['results'] ?? [];

    if (empty($itemIds)) {
        die("<p class='error'>❌ Nenhum item ativo encontrado.</p>");
    }

    echo "<p class='success'>✅ Encontrados: " . implode(', ', $itemIds) . "</p>";
    echo "</div>";

    // Processar cada item
    foreach ($itemIds as $index => $itemId) {
        echo "<div class='section'>";
        echo "<h2>📦 ITEM " . ($index + 1) . ": $itemId</h2>";

        // 1. Buscar detalhes do item
        echo "<h3>🔎 Detalhes do Item</h3>";
        $itemUrl = "https://api.mercadolibre.com/items/$itemId?include_attributes=all";
        echo "<p><strong>URL:</strong> $itemUrl</p>";

        $itemResponse = file_get_contents($itemUrl, false, stream_context_create([
            'http' => [
                'header' => "Authorization: Bearer $access_token"
            ]
        ]));

        $item = json_decode($itemResponse, true);

        echo "<p><strong>Título:</strong> " . ($item['title'] ?? 'N/A') . "</p>";
        echo "<p><strong>date_created RAW:</strong> " . ($item['date_created'] ?? 'N/A') . "</p>";
        echo "<p><strong>Status:</strong> " . ($item['status'] ?? 'N/A') . "</p>";

        // 2. Buscar visitas (com parâmetros de data)
        echo "<h3>👁️ Visitas do Item</h3>";

        $dateTo = date('Y-m-d');
        $dateFrom = date('Y-m-d', strtotime('-365 days'));


        $visitsUrl = "https://api.mercadolibre.com/items/$itemId/visits?date_from=$dateFrom&date_to=$dateTo";
        echo "<p><strong>URL:</strong> $visitsUrl</p>";
        echo "<p><strong>date_from:</strong> $dateFrom</p>";
        echo "<p><strong>date_to:</strong> $dateTo</p>";

        $visitsResponse = @file_get_contents($visitsUrl, false, stream_context_create([
            'http' => [
                'header' => "Authorization: Bearer $access_token",
                'ignore_errors' => true
            ]
        ]));

        if ($visitsResponse) {
            echo "<p class='success'>✅ Resposta recebida!</p>";
            echo "<pre>" . htmlspecialchars($visitsResponse) . "</pre>";

            $visitsData = json_decode($visitsResponse, true);
            $totalVisits = $visitsData['total_visits'] ?? 0;
            echo "<p class='info'><strong>total_visits extraído:</strong> $totalVisits</p>";
        } else {
            echo "<p class='error'>❌ Falha ao buscar visitas</p>";
            $totalVisits = 0;
        }

        // 3. Preparar dados para o banco
        echo "<h3>💾 Preparando para Salvar no Banco</h3>";

        $dataToSave = [
            'account_id' => $account_id,
            'ml_id' => $itemId,
            'title' => $item['title'] ?? '',
            'price' => $item['price'] ?? 0,
            'status' => $item['status'] ?? '',
            'date_created' => $item['date_created'] ?? null,
            'total_visits' => $totalVisits,
            'sold_quantity' => $item['sold_quantity'] ?? 0,
            'available_quantity' => $item['available_quantity'] ?? 0
        ];

        echo "<pre>";
        print_r($dataToSave);
        echo "</pre>";

        // 4. Executar INSERT/UPDATE
        echo "<h3>📝 Executando SQL...</h3>";

        $sql = "INSERT INTO items (
            account_id, ml_id, title, price, status, permalink, thumbnail,
            sold_quantity, available_quantity, shipping_mode, logistic_type,
            free_shipping, date_created, updated_at,
            secure_thumbnail, health, catalog_listing, original_price, currency_id,
            total_visits, last_sale_date
        ) VALUES (
            :acc, :id, :title, :price, :status, :link, :thumb,
            :sold, :avail, :ship_mode, :log_type,
            :free, :date, NOW(),
            :sec_thumb, :health, :cat_list, :orig, :curr,
            :visits, :sale_date
        ) ON CONFLICT (ml_id) DO UPDATE SET
            title = EXCLUDED.title,
            price = EXCLUDED.price,
            status = EXCLUDED.status,
            sold_quantity = EXCLUDED.sold_quantity,
            available_quantity = EXCLUDED.available_quantity,
            date_created = COALESCE(items.date_created, EXCLUDED.date_created),
            total_visits = EXCLUDED.total_visits,
            updated_at = NOW()";

        echo "<pre>" . htmlspecialchars($sql) . "</pre>";

        try {
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                ':acc' => $account_id,
                ':id' => $itemId,
                ':title' => $item['title'] ?? '',
                ':price' => $item['price'] ?? 0,
                ':status' => $item['status'] ?? '',
                ':link' => $item['permalink'] ?? '',
                ':thumb' => $item['thumbnail'] ?? '',
                ':sold' => $item['sold_quantity'] ?? 0,
                ':avail' => $item['available_quantity'] ?? 0,
                ':ship_mode' => $item['shipping']['mode'] ?? null,
                ':log_type' => $item['shipping']['logistic_type'] ?? null,
                ':free' => ($item['shipping']['free_shipping'] ?? false) ? 1 : 0,
                ':date' => $item['date_created'] ?? null,
                ':sec_thumb' => $item['pictures'][0]['secure_url'] ?? null,
                ':health' => $item['health'] ?? 0,
                ':cat_list' => ($item['catalog_listing'] ?? false) ? 'true' : 'false',
                ':orig' => $item['original_price'] ?? null,
                ':curr' => $item['currency_id'] ?? 'BRL',
                ':visits' => $totalVisits,
                ':sale_date' => null
            ]);

            if ($result) {
                echo "<p class='success'>✅ SQL executado com sucesso!</p>";
            } else {
                echo "<p class='error'>❌ Falha ao executar SQL</p>";
                echo "<pre>" . print_r($stmt->errorInfo(), true) . "</pre>";
            }
        } catch (Exception $e) {
            echo "<p class='error'>❌ ERRO SQL: " . $e->getMessage() . "</p>";
        }

        // 5. Verificar o que foi salvo
        echo "<h3>✅ Verificando Banco de Dados</h3>";
        $checkStmt = $pdo->prepare("SELECT ml_id, title, date_created, total_visits, updated_at FROM items WHERE ml_id = :id LIMIT 1");
        $checkStmt->execute([':id' => $itemId]);
        $savedItem = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($savedItem) {
            echo "<p class='success'>✅ Item encontrado no banco!</p>";
            echo "<pre>";
            print_r($savedItem);
            echo "</pre>";

            // Comparar
            if ($savedItem['date_created'] === null) {
                echo "<p class='error'>⚠️ date_created está NULL no banco!</p>";
            } else {
                echo "<p class='success'>✅ date_created salvo: {$savedItem['date_created']}</p>";
            }

            if ($savedItem['total_visits'] == 0) {
                echo "<p class='error'>⚠️ total_visits está 0 no banco!</p>";
            } else {
                echo "<p class='success'>✅ total_visits salvo: {$savedItem['total_visits']}</p>";
            }
        } else {
            echo "<p class='error'>❌ Item NÃO encontrado no banco!</p>";
        }

        echo "</div>";
    }

} catch (Exception $e) {
    echo "<div class='section'>";
    echo "<p class='error'>❌ Erro Fatal: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    echo "</div>";
}

echo "<div class='section'>";
echo "<h2>🏁 Debug Completo</h2>";
echo "<p>Verifique os dados acima para identificar onde está o problema.</p>";
echo "</div>";
