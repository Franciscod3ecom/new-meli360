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

// --- Logger para Depuração ---
if (!function_exists('syncLog')) {
    function syncLog($msg)
    {
        $logFile = __DIR__ . '/sync_debug.log';
        $timestamp = date('Y-m-d H:i:s');
        @file_put_contents($logFile, "[$timestamp] $msg\n", FILE_APPEND);
    }
}
syncLog("--- Nova Requisição de Sync ---");
syncLog("Session ID: " . session_id());
syncLog("Session user_id: " . ($_SESSION['user_id'] ?? 'NULL'));
syncLog("Session selected_account: " . ($_SESSION['selected_account_id'] ?? 'NULL'));
syncLog("GET params: " . json_encode($_GET));

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
        syncLog("ERRO: Nenhuma conta resolvida.");
        http_response_code(400);
        throw new Exception('Nenhuma conta selecionada.');
    }
    syncLog("Conta resolvida: ID=$account_id, ML_ID=$ml_user_id, Nickname=" . ($account['nickname'] ?? 'N/A'));

    // --- Parâmetros de Paginação ---
    $offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;
    $limit = 10; // Reduzido para 10 para evitar timeout com as novas requisições de frete

    // --- Helper Function for curl ---
    function curlGet($url, $token)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $token",
            "User-Agent: Meli360-App/1.1 (ManualSync)"
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['code' => $code, 'body' => json_decode($res, true)];
    }

    // --- Helper: Refresh Token ---
    function doRefresh($pdo, $refresh_token, $ml_user_id)
    {
        $config = require __DIR__ . '/../config/config.php';
        $ch = curl_init("https://api.mercadolibre.com/oauth/token");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'refresh_token',
            'client_id' => $config['MELI_APP_ID'],
            'client_secret' => $config['MELI_SECRET_KEY'],
            'refresh_token' => $refresh_token
        ]));
        $data = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (isset($data['access_token'])) {
            $sql = "UPDATE accounts SET access_token = :a, refresh_token = :r, expires_at = NOW() + INTERVAL '6 hours', updated_at = NOW() WHERE ml_user_id = :u";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':a' => $data['access_token'], ':r' => $data['refresh_token'], ':u' => $ml_user_id]);
            return $data['access_token'];
        }
        return false;
    }

    // --- Buscar IDs de Itens ---
    $resActive = curlGet("https://api.mercadolibre.com/users/$ml_user_id/items/search?status=active", $access_token);

    // Auto-Refresh Logic
    if ($resActive['code'] == 401 && !empty($account['refresh_token'])) {
        $newToken = doRefresh($pdo, $account['refresh_token'], $ml_user_id);
        if ($newToken) {
            $access_token = $newToken;
            $resActive = curlGet("https://api.mercadolibre.com/users/$ml_user_id/items/search?status=active", $access_token);
        }
    }

    $resPaused = curlGet("https://api.mercadolibre.com/users/$ml_user_id/items/search?status=paused", $access_token);

    $totalActive = $resActive['body']['paging']['total'] ?? 0;
    $totalPaused = $resPaused['body']['paging']['total'] ?? 0;
    $totalItems = $totalActive + $totalPaused;

    $diagnostics = [
        'user_id_session' => $_SESSION['user_id'] ?? null,
        'ml_user_id' => $ml_user_id,
        'active_search' => ['code' => $resActive['code'], 'total' => $totalActive],
        'paused_search' => ['code' => $resPaused['code'], 'total' => $totalPaused]
    ];

    // Decidir quais IDs processar baseando no offset global
    $itemsIDs = [];
    if ($offset < $totalActive) {
        // Ainda buscando nos ativos
        $res = curlGet("https://api.mercadolibre.com/users/$ml_user_id/items/search?status=active&limit=$limit&offset=$offset", $access_token);
        $itemsIDs = $res['body']['results'] ?? [];
    } else {
        // Buscando nos pausados
        $pausedOffset = $offset - $totalActive;
        $res = curlGet("https://api.mercadolibre.com/users/$ml_user_id/items/search?status=paused&limit=$limit&offset=$pausedOffset", $access_token);
        $itemsIDs = $res['body']['results'] ?? [];
    }

    if (empty($itemsIDs) && $offset < $totalItems) {
        // Caso de borda (ex: itens deletados no meio), apenas pula pro próximo se não for o fim real
        echo json_encode(['success' => true, 'completed' => false, 'processed' => $offset + $limit, 'total' => $totalItems]);
        exit;
    }

    if (empty($itemsIDs)) {
        echo json_encode([
            'success' => true,
            'completed' => true,
            'message' => 'Sincronização completa',
            'processed' => $offset,
            'total' => $totalItems,
            'debug' => $diagnostics
        ]);
        exit;
    }

    // --- Individual Visits ---
    $visitsMap = [];
    $dateTo = date('Y-m-d');
    $dateFrom = date('Y-m-d', strtotime('-365 days'));

    foreach ($itemsIDs as $itemId) {
        $resVisits = curlGet("https://api.mercadolibre.com/items/$itemId/visits?date_from=$dateFrom&date_to=$dateTo", $access_token);
        if ($resVisits['code'] == 200 && isset($resVisits['body']['total_visits'])) {
            $visitsMap[$itemId] = (int) $resVisits['body']['total_visits'];
        }
        usleep(20000); // 20ms delay
    }

    // --- Helper Functions for Freight ---
    function requestAPI($endpoint, $token)
    {
        $ch = curl_init("https://api.mercadolibre.com$endpoint");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['code' => $code, 'body' => json_decode($res, true)];
    }

    function buscarFretePorCep($itemId, $cep, $token)
    {
        $res = requestAPI("/items/$itemId/shipping_options?zip_code=$cep", $token);
        if ($res['code'] == 200 && isset($res['body']['options'][0]['list_cost'])) {
            return $res['body']['options'][0]['list_cost'];
        }
        return null;
    }

    function buscarEnvioNacional($itemId, $userId, $token)
    {
        $res = requestAPI("/users/$userId/shipping_options/free?item_id=$itemId", $token);
        if ($res['code'] == 200 && isset($res['body']['coverage']['all_country'])) {
            return [
                'custo' => $res['body']['coverage']['all_country']['list_cost'] ?? null,
                'peso' => $res['body']['coverage']['all_country']['billable_weight'] ?? null
            ];
        }
        return null;
    }

    function buscarCategoriaDetalhes($categoryId, $token)
    {
        $res = requestAPI("/categories/$categoryId/shipping_preferences", $token);
        if ($res['code'] == 200)
            return $res['body'];
        return null;
    }

    function buscarCategoriaNome($categoryId, $token)
    {
        $res = requestAPI("/categories/$categoryId", $token);
        if ($res['code'] == 200 && isset($res['body']['name']))
            return $res['body']['name'];
        return null;
    }

    function calcularStatusPeso($pesoIdeal, $pesoFaturavel)
    {
        if (!$pesoIdeal || !$pesoFaturavel)
            return 'N/A';
        if ($pesoFaturavel == $pesoIdeal)
            return '🟡 Peso aceitável';
        if ($pesoFaturavel > $pesoIdeal)
            return '🔴 Peso alto e errado';
        if ($pesoFaturavel < $pesoIdeal)
            return '🟢 Peso baixo e bom';
        return 'N/A';
    }

    function calcularFreteCategoriaAverage($item, $userId, $token)
    {
        $dimensions = $item['category_dimensions'] ?? null;
        if (!$dimensions)
            return null;

        $dimObj = json_decode($dimensions, true);
        if (!$dimObj || empty($dimObj['height']))
            return null;

        $dimString = "{$dimObj['height']}x{$dimObj['width']}x{$dimObj['length']},{$dimObj['weight']}";
        $itemPrice = $item['price'] ?? 100;
        $listingType = $item['listing_type_id'] ?? 'gold_pro';
        $mode = $item['shipping']['mode'] ?? 'me2';
        $condition = $item['condition'] ?? 'new';
        $logisticType = $item['shipping']['logistic_type'] ?? 'drop_off';
        $categoryId = $item['category_id'];

        $endpoint = "/users/$userId/shipping_options/free?dimensions=$dimString&verbose=true&item_price=$itemPrice&listing_type_id=$listingType&mode=$mode&condition=$condition&logistic_type=$logisticType&category_id=$categoryId&currency_id=BRL&seller_status=platinum&reputation=green";

        $res = requestAPI($endpoint, $token);
        if ($res['code'] == 200 && isset($res['body']['coverage']['all_country']['list_cost'])) {
            return $res['body']['coverage']['all_country']['list_cost'];
        }
        return null;
    }

    $CEPS_REGIONAIS = [
        '70002900' => 'brasilia',
        '01001000' => 'sao_paulo',
        '40020210' => 'salvador',
        '69005070' => 'manaus',
        '90010190' => 'porto_alegre'
    ];

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
                'http' => ['header' => "Authorization: Bearer $access_token"]
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

        // Freight logic
        $categoryId = $item['category_id'] ?? null;
        $categoryName = null;
        $envioNacional = null;
        $pesoFaturavel = null;
        $custoEnvio = null;
        $fretes = ['brasilia' => null, 'sao_paulo' => null, 'salvador' => null, 'manaus' => null, 'porto_alegre' => null];
        $statusPeso = 'N/A';
        $me2Restrictions = null;
        $categoryDimensions = null;
        $categoryLogistics = null;
        $categoryRestricted = false;
        $categoryLastModified = null;
        $avgCategoryFreight = null;

        if ($categoryId) {
            $categoryName = buscarCategoriaNome($categoryId, $access_token);
            $envioNacional = buscarEnvioNacional($itemId, $ml_user_id, $access_token);
            if ($envioNacional) {
                $pesoFaturavel = $envioNacional['peso'];
                $custoEnvio = $envioNacional['custo'];
            }
            $categoriaDetalhes = buscarCategoriaDetalhes($categoryId, $access_token);
            if ($categoriaDetalhes) {
                $categoryDimensions = isset($categoriaDetalhes['dimensions']) ? json_encode($categoriaDetalhes['dimensions']) : null;
                $categoryLogistics = isset($categoriaDetalhes['logistics']) ? json_encode($categoriaDetalhes['logistics']) : null;
                $categoryRestricted = !empty($categoriaDetalhes['restricted']);
                $categoryLastModified = $categoriaDetalhes['last_modified'] ?? null;

                $pesoIdeal = $categoriaDetalhes['dimensions']['weight'] ?? null;
                $statusPeso = calcularStatusPeso($pesoIdeal, $pesoFaturavel);
                $me2Restrictions = isset($categoriaDetalhes['me2_restrictions']) ? json_encode($categoriaDetalhes['me2_restrictions']) : null;

                // Calcular Preço Médio da Categoria
                $item['category_dimensions'] = $categoryDimensions;
                $avgCategoryFreight = calcularFreteCategoriaAverage($item, $ml_user_id, $access_token);
            }
            foreach ($CEPS_REGIONAIS as $cep => $key) {
                $fretes[$key] = buscarFretePorCep($itemId, $cep, $access_token);
            }
        }

        // Upsert no Banco
        $sql = "INSERT INTO items (
            account_id, ml_id, title, price, status, permalink, thumbnail,
            sold_quantity, available_quantity, shipping_mode, logistic_type,
            free_shipping, date_created, updated_at,
            secure_thumbnail, health, catalog_listing, original_price, currency_id,
            total_visits, last_sale_date,
            category_name, shipping_cost_nacional, billable_weight, weight_status,
            freight_brasilia, freight_sao_paulo, freight_salvador, freight_manaus, freight_porto_alegre,
            me2_restrictions,
            category_id, category_dimensions, category_logistics, category_restricted,
            category_last_modified, avg_category_freight
        ) VALUES (
            :acc, :id, :title, :price, :status, :link, :thumb,
            :sold, :avail, :ship_mode, :log_type,
            :free, :date, NOW(),
            :sec_thumb, :health, :cat_list, :orig, :curr,
            :visits, :sale_date,
            :cat_name, :ship_cost, :weight, :weight_status,
            :frete_bsb, :frete_sp, :frete_ssa, :frete_mao, :frete_poa,
            :me2_rest,
            :cat_id, :cat_dims, :cat_logs, :cat_restr, :cat_last_mod, :avg_cat_freight
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
            category_name = COALESCE(EXCLUDED.category_name, items.category_name),
            shipping_cost_nacional = COALESCE(EXCLUDED.shipping_cost_nacional, items.shipping_cost_nacional),
            billable_weight = COALESCE(EXCLUDED.billable_weight, items.billable_weight),
            weight_status = COALESCE(EXCLUDED.weight_status, items.weight_status),
            freight_brasilia = COALESCE(EXCLUDED.freight_brasilia, items.freight_brasilia),
            freight_sao_paulo = COALESCE(EXCLUDED.freight_sao_paulo, items.freight_sao_paulo),
            freight_salvador = COALESCE(EXCLUDED.freight_salvador, items.freight_salvador),
            freight_manaus = COALESCE(EXCLUDED.freight_manaus, items.freight_manaus),
            freight_porto_alegre = COALESCE(EXCLUDED.freight_porto_alegre, items.freight_porto_alegre),
            me2_restrictions = COALESCE(EXCLUDED.me2_restrictions, items.me2_restrictions),
            category_id = EXCLUDED.category_id,
            category_dimensions = EXCLUDED.category_dimensions,
            category_logistics = EXCLUDED.category_logistics,
            category_restricted = EXCLUDED.category_restricted,
            category_last_modified = EXCLUDED.category_last_modified,
            avg_category_freight = EXCLUDED.avg_category_freight,
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
            ':sale_date' => $lastSale,
            ':cat_name' => $categoryName,
            ':ship_cost' => $custoEnvio,
            ':weight' => $pesoFaturavel,
            ':weight_status' => $statusPeso,
            ':frete_bsb' => $fretes['brasilia'],
            ':frete_sp' => $fretes['sao_paulo'],
            ':frete_ssa' => $fretes['salvador'],
            ':frete_mao' => $fretes['manaus'],
            ':frete_poa' => $fretes['porto_alegre'],
            ':me2_rest' => $me2Restrictions,
            ':cat_id' => $categoryId,
            ':cat_dims' => $categoryDimensions,
            ':cat_logs' => $categoryLogistics,
            ':cat_restr' => $categoryRestricted ? 1 : 0,
            ':cat_last_mod' => $categoryLastModified,
            ':avg_cat_freight' => $avgCategoryFreight
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
