<?php
// backend/cron/sync.php
// SYNC V2 (PRODUCTION SAFE MODE)
// Versão otimizada para evitar Timeouts e Erros 500
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('memory_limit', '512M');
set_time_limit(300); // 5 min

header('Content-Type: text/plain; charset=utf-8');

session_start();

require_once __DIR__ . '/../config/database.php';

// Safe Load Config
$configFile = __DIR__ . '/../config/config.php';
if (!file_exists($configFile))
    die("Config missing");
$config = require $configFile;

$APP_ID = $config['MELI_APP_ID'];
$SECRET_KEY = $config['MELI_SECRET_KEY'];

function logAndFlush($msg)
{
    echo "[" . date('H:i:s') . "] $msg\n";
    flush();
    if (ob_get_level() > 0)
        ob_flush();
}

$BATCH_LIMIT = 20; // Aumentado para produção (era 5 no teste)

// CEPs fixos para simulação de frete regional
$CEPS_REGIONAIS = [
    '70002900' => 'Brasília-DF',
    '01001000' => 'São Paulo-SP',
    '40020210' => 'Salvador-BA',
    '69005070' => 'Manaus-AM',
    '90010190' => 'Porto Alegre-RS'
];

echo "=== SYNC V3 (FREIGHT SYSTEM) ===\n";
echo "Batch Limit: $BATCH_LIMIT items\n\n";

try {
    // 1. Session Check
    if (!isset($_SESSION['user_id'])) {
        die("ERRO: Nenhuma sessão ativa. Por favor, faça login novamente no Painel Administrativo.\n");
    }
    $ml_user_id = $_SESSION['user_id'];
    logAndFlush("Usuário: $ml_user_id");

    // 2. DB Connection
    $pdo = getDatabaseConnection();

    // 3. Resolve Account and Tokens
    $account = null;

    // Priority 1: Selected Account in Session
    if (!empty($_SESSION['selected_account_id'])) {
        $stmt = $pdo->prepare("SELECT * FROM accounts WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $_SESSION['selected_account_id']]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    // Priority 2: Native User Fallback (First linked account)
    elseif (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'native') {
        $stmt = $pdo->prepare("
            SELECT a.* 
            FROM accounts a
            JOIN user_accounts ua ON a.id = ua.account_id
            WHERE ua.user_id = :user_id
            ORDER BY a.nickname LIMIT 1
        ");
        $stmt->execute([':user_id' => $_SESSION['user_id']]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);

        // Auto-select for next time
        if ($account) {
            $_SESSION['selected_account_id'] = $account['id'];
        }
    }
    // Priority 3: Legacy OAuth (Session ID is ML ID)
    else {
        $stmt = $pdo->prepare("SELECT * FROM accounts WHERE ml_user_id = :id LIMIT 1");
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$account) {
        die("ERRO: Nenhuma conta do Mercado Livre vinculada ou selecionada. Por favor, conecte uma conta no painel.\n");
    }

    $ml_user_id = $account['ml_user_id']; // Override session ID with actual ML ID
    $access_token = $account['access_token'];
    $refresh_token = $account['refresh_token'];
    $account_id = $account['id'];

    logAndFlush("Conta Selecionada: {$account['nickname']} (ML ID: $ml_user_id)");

    // Helper: Refresh Token
    function doRefresh($pdo, $refresh_token, $app_id, $secret, $ml_user_id)
    {
        logAndFlush("Token expirado. Renovando...");
        $ch = curl_init("https://api.mercadolibre.com/oauth/token");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'refresh_token',
            'client_id' => $app_id,
            'client_secret' => $secret,
            'refresh_token' => $refresh_token
        ]));
        $data = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (isset($data['access_token'])) {
            $sql = "UPDATE accounts SET access_token = :a, refresh_token = :r, expires_at = NOW() + INTERVAL '6 hours', updated_at = NOW() WHERE ml_user_id = :u";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':a' => $data['access_token'], ':r' => $data['refresh_token'], ':u' => $ml_user_id]);
            logAndFlush("Token Renovado com Sucesso!");
            return $data['access_token'];
        }
        logAndFlush("ERRO DE RENOVAÇÃO: " . ($data['message'] ?? 'Unknown'));
        return false;
    }

    // Helper: Safe Request
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

    // Helper: Buscar custo de frete para um CEP específico
    function buscarFretePorCep($itemId, $cep, $token)
    {
        $res = requestAPI("/items/$itemId/shipping_options?zip_code=$cep", $token);
        if ($res['code'] == 200 && isset($res['body']['options'][0]['list_cost'])) {
            return $res['body']['options'][0]['list_cost'];
        }
        return null;
    }

    // Helper: Buscar envio nacional (peso e custo médio)
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

    // Helper: Buscar detalhes da categoria (peso ideal)
    function buscarCategoriaDetalhes($categoryId, $token)
    {
        $res = requestAPI("/categories/$categoryId/shipping_preferences", $token);
        if ($res['code'] == 200) {
            return $res['body'];
        }
        return null;
    }

    // Helper: Buscar nome da categoria
    function buscarCategoriaNome($categoryId, $token)
    {
        $res = requestAPI("/categories/$categoryId", $token);
        if ($res['code'] == 200 && isset($res['body']['name'])) {
            return $res['body']['name'];
        }
        return null;
    }

    // Helper: Calcular status do peso
    function calcularStatusPeso($pesoIdeal, $pesoFaturavel)
    {
        if (!$pesoIdeal || !$pesoFaturavel) {
            return 'N/A';
        }
        if ($pesoFaturavel == $pesoIdeal) {
            return '🟡 Peso aceitável';
        }
        if ($pesoFaturavel > $pesoIdeal) {
            return '🔴 Peso alto e errado';
        }
        if ($pesoFaturavel < $pesoIdeal) {
            return '🟢 Peso baixo e bom';
        }
        return 'N/A';
    }

    // 4. Processing Items (Pagination Loop)
    $offset = 0;
    $limit = 50;
    $totalProcessed = 0;

    logAndFlush("Iniciando sincronização completa...");

    do {
        logAndFlush("Buscando página (Offset: $offset, Limit: $limit)...");
        $res = requestAPI("/users/$ml_user_id/items/search?status=active&limit=$limit&offset=$offset", $access_token);

        // Auto-Refresh Logic on 401 (Retry once)
        if ($res['code'] == 401) {
            $access_token = doRefresh($pdo, $refresh_token, $APP_ID, $SECRET_KEY, $ml_user_id);
            if (!$access_token)
                die("Falha fatal de autenticação.\n");
            $res = requestAPI("/users/$ml_user_id/items/search?status=active&limit=$limit&offset=$offset", $access_token);
        }

        $itemsIDs = $res['body']['results'] ?? [];
        if (empty($itemsIDs)) {
            break; // No more items
        }

        // Processing Batch
        // 5. Processing Batch - PREPARE VISITS BATCH
        $idsString = implode(',', $itemsIDs);

        // Batch Visits
        $dateTo = date('Y-m-d');
        $dateFrom = date('Y-m-d', strtotime('-5 years'));

        $visitsMap = [];
        $rVisits = requestAPI("/items/visits?ids=$idsString&date_from=$dateFrom&date_to=$dateTo", $access_token);

        if ($rVisits['code'] == 200 && is_array($rVisits['body'])) {
            foreach ($rVisits['body'] as $visitData) {
                $visitsMap[$visitData['item_id']] = $visitData['total_visits'] ?? 0;
            }
        }

        foreach ($itemsIDs as $itemId) {
            // Item Details
            $rItem = requestAPI("/items/$itemId?include_attributes=all", $access_token);
            $item = $rItem['body'];
            if (!$item)
                continue;

            // Get Visits from Batch Map
            $visits = $visitsMap[$itemId] ?? 0;

            // Last Sale (Robust Check)
            $lastSale = null;
            if (($item['sold_quantity'] ?? 0) > 0) {
                $rOrders = requestAPI("/orders/search?seller=$ml_user_id&item=$itemId&sort=date_desc&limit=1", $access_token);
                if (!empty($rOrders['body']['results'])) {
                    $lastSale = $rOrders['body']['results'][0]['date_closed'];
                }
            }

            // Image Logic
            $secure_thumbnail = $item['thumbnail'];
            if (!empty($item['pictures'][0]['secure_url'])) {
                $secure_thumbnail = $item['pictures'][0]['secure_url'];
            }

            // === FREIGHT SYSTEM ===
            $categoryId = $item['category_id'] ?? null;
            $categoryName = null;
            $envioNacional = null;
            $pesoFaturavel = null;
            $custoEnvio = null;
            $fretes = ['brasilia' => null, 'sao_paulo' => null, 'salvador' => null, 'manaus' => null, 'porto_alegre' => null];
            $statusPeso = 'N/A';
            $me2Restrictions = null;

            if ($categoryId) {
                $categoryName = buscarCategoriaNome($categoryId, $access_token);
                $envioNacional = buscarEnvioNacional($itemId, $ml_user_id, $access_token);
                if ($envioNacional) {
                    $pesoFaturavel = $envioNacional['peso'];
                    $custoEnvio = $envioNacional['custo'];
                }
                $categoriaDetalhes = buscarCategoriaDetalhes($categoryId, $access_token);
                if ($categoriaDetalhes) {
                    $pesoIdeal = $categoriaDetalhes['dimensions']['weight'] ?? null;
                    $statusPeso = calcularStatusPeso($pesoIdeal, $pesoFaturavel);
                    $me2Restrictions = isset($categoriaDetalhes['me2_restrictions']) ? json_encode($categoriaDetalhes['me2_restrictions']) : null;
                }
                foreach ($CEPS_REGIONAIS as $cep => $cidade) {
                    $key = strtolower(str_replace(['-', ' '], ['_', '_'], explode('-', $cidade)[0]));
                    $fretes[$key] = buscarFretePorCep($itemId, $cep, $access_token);
                }
            }

            // DB Upsert
            try {
                $sql = "INSERT INTO items (
                    account_id, ml_id, title, price, status, permalink, thumbnail,
                    sold_quantity, available_quantity, shipping_mode, logistic_type,
                    free_shipping, date_created, updated_at,
                    secure_thumbnail, health, catalog_listing, original_price, currency_id,
                    total_visits, last_sale_date,
                    category_name, shipping_cost_nacional, billable_weight, weight_status,
                    freight_brasilia, freight_sao_paulo, freight_salvador, freight_manaus, freight_porto_alegre,
                    me2_restrictions
                ) VALUES (
                    :acc, :id, :title, :price, :status, :link, :thumb,
                    :sold, :avail, :ship_mode, :log_type,
                    :free, :date, NOW(),
                    :sec_thumb, :health, :cat_list, :orig, :curr,
                    :visits, :sale_date,
                    :cat_name, :ship_cost, :weight, :weight_status,
                    :frete_bsb, :frete_sp, :frete_ssa, :frete_mao, :frete_poa,
                    :me2_rest
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
                    total_visits = EXCLUDED.total_visits,
                    last_sale_date = EXCLUDED.last_sale_date,
                    category_name = EXCLUDED.category_name,
                    shipping_cost_nacional = EXCLUDED.shipping_cost_nacional,
                    billable_weight = EXCLUDED.billable_weight,
                    weight_status = EXCLUDED.weight_status,
                    freight_brasilia = EXCLUDED.freight_brasilia,
                    freight_sao_paulo = EXCLUDED.freight_sao_paulo,
                    freight_salvador = EXCLUDED.freight_salvador,
                    freight_manaus = EXCLUDED.freight_manaus,
                    freight_porto_alegre = EXCLUDED.freight_porto_alegre,
                    me2_restrictions = EXCLUDED.me2_restrictions,
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
                    ':me2_rest' => $me2Restrictions
                ]);
                $totalProcessed++;
                echo ".";
                if ($totalProcessed % 10 == 0)
                    echo " ($totalProcessed) ";
                flush();
            } catch (PDOException $e) {
                // Log duplication error
            }
        }

        $offset += $limit;

        // Safety Break (Prevent extremely long executions)
        if ($offset >= 1000) {
            logAndFlush("\nLimite de segurança de 1000 itens atingido. Execute novamente para continuar.");
            break;
        }

    } while (count($itemsIDs) == $limit);

    echo "\n\n=== SYNC COMPLETADO: $totalProcessed itens processados ===\n";

} catch (Throwable $t) {
    echo "\n[FATAL ERROR] " . $t->getMessage();
}
