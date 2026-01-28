<?php
// backend/debug_sync.php
// Script de Diagnóstico para Sync de Dados
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/config/database.php';

echo "<h1>🕵️ Debug Sync Data</h1>";

session_start();

if (!isset($_SESSION['user_id'])) {
    die("❌ Faça login primeiro.");
}

try {
    $pdo = getDatabaseConnection();

    // 1. RESOLVER CONTA
    echo "<h3>1. Verificando Conta...</h3>";
    $account = null;

    if (!empty($_SESSION['selected_account_id'])) {
        echo "Busca por selected_account_id: {$_SESSION['selected_account_id']}<br>";
        $stmt = $pdo->prepare("SELECT * FROM accounts WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $_SESSION['selected_account_id']]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
    } elseif (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'native') {
        echo "Busca por usuário nativo (fallback)...<br>";
        $stmt = $pdo->prepare("
            SELECT a.* FROM accounts a
            JOIN user_accounts ua ON a.id = ua.account_id
            WHERE ua.user_id = :user_id
            ORDER BY a.nickname LIMIT 1
        ");
        $stmt->execute([':user_id' => $_SESSION['user_id']]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        echo "Busca por login OAuth direto...<br>";
        $stmt = $pdo->prepare("SELECT * FROM accounts WHERE ml_user_id = :id LIMIT 1");
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$account) {
        die("❌ Nenhuma conta encontrada vinculada a este usuário!<br>");
    }

    echo "✅ Conta encontrada: <strong>{$account['nickname']}</strong> (ML ID: {$account['ml_user_id']})<br>";
    $access_token = $account['access_token'];
    $ml_user_id = $account['ml_user_id'];

    // 2. BUSCAR 1 ITEM
    echo "<h3>2. Buscando 1 Item na API...</h3>";

    function req($url, $token)
    {
        $ch = curl_init("https://api.mercadolibre.com" . $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);
        $res = curl_exec($ch);
        curl_close($ch);
        return json_decode($res, true);
    }

    $search = req("/users/$ml_user_id/items/search?status=active&limit=1", $access_token);

    if (empty($search['results'])) {
        die("⚠️ Nenhum item ativo encontrado na API do Mercado Livre para esta conta.<br>");
    }

    $itemId = $search['results'][0];
    echo "✅ Item ID encontrado: $itemId<br>";

    // 3. DETALHES DO ITEM
    echo "<h3>3. Buscando Detalhes do Item...</h3>";
    $item = req("/items/$itemId?include_attributes=all", $access_token);

    echo "<strong>Dados brutos da API (Parcial):</strong><pre>";
    echo "Title: " . ($item['title'] ?? 'N/A') . "\n";
    echo "Date Created: " . ($item['date_created'] ?? 'N/A') . "\n";
    echo "Shipping Mode: " . ($item['shipping']['mode'] ?? 'N/A') . "\n";
    echo "Listing Type: " . ($item['listing_type_id'] ?? 'N/A') . "\n";
    echo "</pre>";

    // 4. VISITAS (Usando endpoint novo /items/visits?ids=...&date_from=...&date_to=...)
    echo "<h3>4. Buscando Visitas...</h3>";

    // Determinar janela de tempo (da criação até hoje)
    $dateFrom = isset($item['date_created']) ? date('Y-m-d', strtotime($item['date_created'])) : '2020-01-01';
    $dateTo = date('Y-m-d');

    $visitsUrl = "/items/visits?ids=$itemId&date_from=$dateFrom&date_to=$dateTo";
    echo "URL: $visitsUrl<br>";

    $visitsRes = req($visitsUrl, $access_token);
    echo "Resposta Visitas: <pre>" . print_r($visitsRes, true) . "</pre>";

    $visitCount = 0;
    if (isset($visitsRes[$itemId])) {
        $visitCount = $visitsRes[$itemId]['total_visits'] ?? 0;
    } elseif (isset($visitsRes[0]['total_visits'])) {
        $visitCount = $visitsRes[0]['total_visits'] ?? 0;
    }

    echo "✅ Contagem extraída: $visitCount<br>";

    // 4.1 DEBUG ENVIO NACIONAL
    echo "<h3>4.1 Debugging Shipping Data...</h3>";
    $shipUrl = "/users/$ml_user_id/shipping_options/free?item_id=$itemId";
    echo "Endpoint: $shipUrl<br>";
    $shipRes = req($shipUrl, $access_token);
    echo "Shipping Response: <pre>" . print_r($shipRes, true) . "</pre>";
    // Check if we get expected data
    if (isset($shipRes['coverage']['all_country']['list_cost'])) {
        echo "<strong style='color:green'>✅ Custo de Envio Encontrado: " . $shipRes['coverage']['all_country']['list_cost'] . "</strong><br>";
    } else {
        echo "<strong style='color:orange'>⚠️ Custo não encontrado na resposta.</strong><br>";
    }

    // 5. TESTE DE INSERT
    echo "<h3>5. Simulando Update no Banco...</h3>";

    $sql = "UPDATE items SET 
            date_created = :date,
            total_visits = :visits,
            shipping_mode = :ship_mode
            WHERE ml_id = :id";

    echo "SQL: $sql<br>";
    echo "Params: date={$item['date_created']}, visits=$visitCount, mode={$item['shipping']['mode']}, id=$itemId<br>";

    $stmt = $pdo->prepare($sql);
    try {
        $stmt->execute([
            ':date' => $item['date_created'],
            ':visits' => $visitCount,
            ':ship_mode' => $item['shipping']['mode'],
            ':id' => $itemId
        ]);
        echo "<h2 style='color: green'>✅ Sucesso! Update rodou sem erro SQL.</h2>";
    } catch (PDOException $ex) {
        echo "<h2 style='color: red'>❌ Erro SQL:</h2>";
        echo $ex->getMessage();
    }

} catch (Exception $e) {
    echo "❌ Erro Geral: " . $e->getMessage();
}
