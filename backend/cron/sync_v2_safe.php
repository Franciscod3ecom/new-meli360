<?php
// backend/cron/sync_v2_safe.php
// SAFE MODE SYNC V2 (Small Batch + Verbose Error)
// FIX: Using getDatabaseConnection() properly
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('memory_limit', '256M');
set_time_limit(300); // 5 minutes

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

$START_TIME = time();
$BATCH_LIMIT = 5; // Reduced for safety check

echo "=== SYNC V2 (SAFE MODE) ===\n";
echo "Batch Limit: $BATCH_LIMIT items\n\n";

try {
    // 1. Session Check
    if (!isset($_SESSION['user_id'])) {
        die("ERRO: Nenhuma sessão ativa. Faça login no Meli360 primeiro.\n");
    }
    $ml_user_id = $_SESSION['user_id'];
    logAndFlush("Usuário: $ml_user_id");

    // 2. DB Connection
    logAndFlush("Conectando ao Banco...");
    $pdo = getDatabaseConnection();

    // 3. Account Data
    $stmt = $pdo->prepare("SELECT * FROM accounts WHERE ml_user_id = :id LIMIT 1");
    $stmt->execute([':id' => $ml_user_id]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$account)
        die("Conta não encontrada no DB.\n");

    $access_token = $account['access_token'];
    $refresh_token = $account['refresh_token'];
    $account_id = $account['id'];
    logAndFlush("Conta ID: $account_id");

    // Helper: Refresh Token
    function doRefresh($pdo, $refresh_token, $app_id, $secret, $ml_user_id)
    {
        logAndFlush("Token expirado. Tentando Refresh...");
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
            logAndFlush("Token Renovado!");
            return $data['access_token'];
        }
        logAndFlush("ERRO Refresh: " . ($data['message'] ?? 'Unknown'));
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

    // 4. Listing Items
    logAndFlush("Buscando itens na API...");
    $res = requestAPI("/users/$ml_user_id/items/search?status=active&limit=$BATCH_LIMIT", $access_token);

    if ($res['code'] == 401) {
        $access_token = doRefresh($pdo, $refresh_token, $APP_ID, $SECRET_KEY, $ml_user_id);
        if (!$access_token)
            die("Falha fatal de autenticação.\n");
        $res = requestAPI("/users/$ml_user_id/items/search?status=active&limit=$BATCH_LIMIT", $access_token);
    }

    $items = $res['body']['results'] ?? [];
    if (empty($items))
        die("Nenhum item encontrado.\n");

    logAndFlush("Encontrados: " . count($items) . " (Limitado a $BATCH_LIMIT)");

    // 5. Processing Batch
    $count = 0;
    foreach ($items as $itemId) {
        echo "\nProcessando $itemId... ";

        // Item Details
        $rItem = requestAPI("/items/$itemId?include_attributes=all", $access_token);
        $item = $rItem['body'];
        if (!$item) {
            echo "[ERRO API]";
            continue;
        }

        // Visits
        $rVisits = requestAPI("/items/$itemId/visits", $access_token);
        $visits = $rVisits['body'][$itemId] ?? 0;

        // Last Sale
        $lastSale = null;
        if (($item['sold_quantity'] ?? 0) > 0) {
            $rOrders = requestAPI("/orders/search?seller=$ml_user_id&item=$itemId&sort=date_desc&limit=1", $access_token);
            if (!empty($rOrders['body']['results'])) {
                $lastSale = $rOrders['body']['results'][0]['date_closed'];
            }
        }

        // Logic
        $secure_thumbnail = $item['thumbnail'];
        if (!empty($item['pictures'][0]['secure_url'])) {
            $secure_thumbnail = $item['pictures'][0]['secure_url'];
        }

        // DB Update
        try {
            $sql = "INSERT INTO items (
                account_id, ml_id, title, price, status, permalink, thumbnail,
                sold_quantity, available_quantity, shipping_mode, logistic_type,
                free_shipping, date_created, updated_at,
                secure_thumbnail, health, original_price, currency_id,
                total_visits, last_sale_date
            ) VALUES (
                :acc, :id, :title, :price, :status, :link, :thumb,
                :sold, :avail, :ship_mode, :log_type,
                :free, :date, NOW(),
                :sec_thumb, :health, :orig, :curr,
                :visits, :sale_date
            ) ON CONFLICT (ml_id) DO UPDATE SET
                title = EXCLUDED.title,
                price = EXCLUDED.price,
                status = EXCLUDED.status,
                sold_quantity = EXCLUDED.sold_quantity,
                available_quantity = EXCLUDED.available_quantity,
                secure_thumbnail = EXCLUDED.secure_thumbnail,
                health = EXCLUDED.health,
                original_price = EXCLUDED.original_price,
                currency_id = EXCLUDED.currency_id,
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
                ':orig' => $item['original_price'] ?? null,
                ':curr' => $item['currency_id'],
                ':visits' => $visits,
                ':sale_date' => $lastSale
            ]);
            echo "[OK]";
            $count++;
        } catch (PDOException $e) {
            echo "[DB ERRO: " . $e->getMessage() . "]";
        }
    }

    echo "\n\n=== SYNC SAFE MODE COMPLETED ($count updated) ===\n";

} catch (Throwable $t) {
    echo "\n[FATAL] " . $t->getMessage();
}
