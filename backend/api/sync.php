<?php
/**
 * Arquivo: backend/api/sync.php
 * Endpoint de sincronização por lotes (chunked sync)
 * Processa N itens por requisição para evitar timeout
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://d3ecom.com.br');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('max_execution_time', 60); // 60 segundos por batch

session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

// Verificar sessão
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

try {
    $pdo = getDatabaseConnection();

    // --- Resolver Conta ---
    $account_id = null;
    $access_token = null;
    $ml_user_id = null;

    if (!empty($_SESSION['selected_account_id'])) {
        $stmt = $pdo->prepare("SELECT * FROM accounts WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $_SESSION['selected_account_id']]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($account) {
            $account_id = $account['id'];
            $access_token = $account['access_token'];
            $ml_user_id = $account['ml_user_id'];
        }
    } elseif (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'native') {
        $stmt = $pdo->prepare("
            SELECT a.* FROM accounts a
            JOIN user_accounts ua ON a.id = ua.account_id
            WHERE ua.user_id = :user_id
            ORDER BY a.nickname LIMIT 1
        ");
        $stmt->execute([':user_id' => $_SESSION['user_id']]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($account) {
            $account_id = $account['id'];
            $access_token = $account['access_token'];
            $ml_user_id = $account['ml_user_id'];
        }
    } else {
        $stmt = $pdo->prepare("SELECT * FROM accounts WHERE ml_user_id = :id LIMIT 1");
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($account) {
            $account_id = $account['id'];
            $access_token = $account['access_token'];
            $ml_user_id = $account['ml_user_id'];
        }
    }

    if (!$account_id) {
        http_response_code(400);
        throw new Exception('Nenhuma conta selecionada.');
    }

    // --- Parâmetros de Paginação ---
    $offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;
    $limit = 20; // Processar 20 itens por vez

    // --- Buscar IDs de Itens Ativos e Pausados ---
    // Removemos o filtro fixo de 'active' para pegar também itens 'paused'
    $response = file_get_contents("https://api.mercadolibre.com/users/$ml_user_id/items/search?status=active,paused&limit=$limit&offset=$offset", false, stream_context_create([
        'http' => [
            'header' => "Authorization: Bearer $access_token"
        ]
    ]));

    $data = json_decode($response, true);
    $itemsIDs = $data['results'] ?? [];
    $totalItems = $data['paging']['total'] ?? 0;

    if (empty($itemsIDs)) {
        echo json_encode([
            'success' => true,
            'completed' => true,
            'message' => 'Sincronização completa',
            'processed' => $offset,
            'total' => $totalItems
        ]);
        exit;
    }

    // --- Individual Visits com parâmetros de data corretos ---
    $visitsMap = [];

    // Calcular intervalo de data (últimos 365 dias) - Formato YYYY-MM-DD
    $dateTo = date('Y-m-d');
    $dateFrom = date('Y-m-d', strtotime('-365 days'));


    foreach ($itemsIDs as $itemId) {
        // Endpoint correto com parâmetros de data
        $visitsUrl = "https://api.mercadolibre.com/items/$itemId/visits?date_from=$dateFrom&date_to=$dateTo";

        $visitsResponse = @file_get_contents($visitsUrl, false, stream_context_create([
            'http' => [
                'header' => "Authorization: Bearer $access_token",
                'ignore_errors' => true
            ]
        ]));

        if ($visitsResponse) {
            $visitsData = json_decode($visitsResponse, true);
            // Resposta esperada: {"item_id": "MLB123", "total_visits": 350}
            if (isset($visitsData['total_visits'])) {
                $visitsMap[$itemId] = (int) $visitsData['total_visits'];
            }
        }

        // Pequeno delay para não sobrecarregar a API (50ms)
        usleep(50000);
    }

    // --- Processar Cada Item ---
    $processed = 0;
    foreach ($itemsIDs as $itemId) {
        // Detalhes do Item
        $itemResponse = file_get_contents("https://api.mercadolibre.com/items/$itemId?include_attributes=all", false, stream_context_create([
            'http' => [
                'header' => "Authorization: Bearer $access_token"
            ]
        ]));

        $item = json_decode($itemResponse, true);
        if (!$item)
            continue;

        $visits = $visitsMap[$itemId] ?? 0;

        // Última Venda
        $lastSale = null;
        if (($item['sold_quantity'] ?? 0) > 0) {
            $ordersResponse = @file_get_contents("https://api.mercadolibre.com/orders/search?seller=$ml_user_id&item=$itemId&sort=date_desc&limit=1", false, stream_context_create([
                'http' => [
                    'header' => "Authorization: Bearer $access_token"
                ]
            ]));
            if ($ordersResponse) {
                $ordersData = json_decode($ordersResponse, true);
                if (!empty($ordersData['results'])) {
                    $lastSale = $ordersData['results'][0]['date_closed'];
                }
            }
        }

        // Thumbnail
        $secure_thumbnail = $item['thumbnail'];
        if (!empty($item['pictures'][0]['secure_url'])) {
            $secure_thumbnail = $item['pictures'][0]['secure_url'];
        }

        // Upsert no Banco
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
            secure_thumbnail = EXCLUDED.secure_thumbnail,
            health = EXCLUDED.health,
            catalog_listing = EXCLUDED.catalog_listing,
            original_price = EXCLUDED.original_price,
            currency_id = EXCLUDED.currency_id,
            date_created = COALESCE(items.date_created, EXCLUDED.date_created),
            total_visits = EXCLUDED.total_visits,
            last_sale_date = EXCLUDED.last_sale_date,
            updated_at = NOW()";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':acc' => $account_id,
            ':id' => $itemId,
            ':title' => $item['title'],
            ':price' => $item['price'],
            ':status' => $item['status'],
            ':link' => $item['permalink'],
            ':thumb' => $item['thumbnail'],
            ':sold' => $item['sold_quantity'],
            ':avail' => $item['available_quantity'],
            ':ship_mode' => $item['shipping']['mode'] ?? null,
            ':log_type' => $item['shipping']['logistic_type'] ?? null,
            ':free' => ($item['shipping']['free_shipping'] ?? false) ? 1 : 0,
            ':date' => $item['date_created'],
            ':sec_thumb' => $secure_thumbnail,
            ':health' => $item['health'] ?? 0,
            ':cat_list' => ($item['catalog_listing'] ?? false) ? 'true' : 'false',
            ':orig' => $item['original_price'] ?? null,
            ':curr' => $item['currency_id'],
            ':visits' => $visits,
            ':sale_date' => $lastSale
        ]);

        $processed++;
    }

    // --- Retornar Progresso ---
    $actualProcessed = $offset + $processed; // Quantidade real processada até agora
    $isComplete = $actualProcessed >= $totalItems;

    echo json_encode([
        'success' => true,
        'completed' => $isComplete,
        'processed' => $actualProcessed,
        'total' => $totalItems,
        'batch_size' => $processed,
        'message' => $isComplete ? 'Sincronização completa' : "Processados $actualProcessed de $totalItems itens"
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro no servidor: ' . $e->getMessage()
    ]);
}
