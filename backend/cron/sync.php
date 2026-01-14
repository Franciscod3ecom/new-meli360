<?php
// backend/cron/sync.php - VERSÃO SIMPLIFICADA
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain; charset=utf-8');

session_start();

require_once __DIR__ . '/../config/database.php';

// Configuration
$MAX_EXECUTION_TIME = 50;
$BATCH_SIZE = 50;
$START_TIME = time();

function logMsg($msg) {
    echo "[" . date('Y-m-d H:i:s') . "] $msg\n";
    flush();
}

function callMeliApi($endpoint, $token) {
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api.mercadolibre.com$endpoint",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer $token"],
    ]);
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    if ($httpCode !== 200) {
        logMsg("API Error: HTTP $httpCode - $response");
        return null;
    }
    
    return json_decode($response, true);
}

// --- MAIN LOGIC ---

logMsg("Starting sync...");

// 1. Check Session
if (!isset($_SESSION['user_id'])) {
    die("ERROR: No active session. Please login first.\n");
}

$ml_user_id = $_SESSION['user_id'];
logMsg("Session found: $ml_user_id");

// 2. Get Account from DB
try {
    $pdo = getDatabaseConnection();
    $stmt = $pdo->prepare("SELECT * FROM accounts WHERE ml_user_id = :ml_user_id LIMIT 1");
    $stmt->execute([':ml_user_id' => $ml_user_id]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$account) {
        die("ERROR: Account not found for ml_user_id: $ml_user_id\n");
    }
    
    $account_id = $account['id'];
    $access_token = $account['access_token'];
    logMsg("Account found: $account_id");
    
} catch (Exception $e) {
    die("DB ERROR: " . $e->getMessage() . "\n");
}

// 3. Get total items from ML
logMsg("Fetching items from Mercado Libre...");
$searchData = callMeliApi("/users/$ml_user_id/items/search?status=active&limit=50&offset=0", $access_token);

if (!$searchData) {
    die("ERROR: Failed to fetch items from ML API\n");
}

$total = $searchData['paging']['total'] ?? 0;
$itemIds = $searchData['results'] ?? [];

logMsg("Found $total items on ML. Processing first batch of " . count($itemIds) . " items...");

// 4. Fetch and update each item
$updated = 0;
foreach ($itemIds as $mlId) {
    if (time() - $START_TIME > $MAX_EXECUTION_TIME) {
        logMsg("Time limit reached. Stopping.");
        break;
    }
    
    $itemData = callMeliApi("/items/$mlId", $access_token);
    if (!$itemData) continue;
    
    try {
        // Upsert item
        $sql = "INSERT INTO items (
            account_id, ml_id, title, price, status, permalink, thumbnail,
            sold_quantity, available_quantity, shipping_mode, logistic_type,
            free_shipping, date_created, updated_at
        ) VALUES (
            :account_id, :ml_id, :title, :price, :status, :permalink, :thumbnail,
            :sold_quantity, :available_quantity, :shipping_mode, :logistic_type,
            :free_shipping, :date_created, NOW()
        ) ON CONFLICT (ml_id) DO UPDATE SET
            title = EXCLUDED.title,
            price = EXCLUDED.price,
            status = EXCLUDED.status,
            sold_quantity = EXCLUDED.sold_quantity,
            available_quantity = EXCLUDED.available_quantity,
            updated_at = NOW()";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':account_id' => $account_id,
            ':ml_id' => $itemData['id'],
            ':title' => $itemData['title'],
            ':price' => $itemData['price'],
            ':status' => $itemData['status'],
            ':permalink' => $itemData['permalink'],
            ':thumbnail' => $itemData['thumbnail'] ?? '',
            ':sold_quantity' => $itemData['sold_quantity'] ?? 0,
            ':available_quantity' => $itemData['available_quantity'] ?? 0,
            ':shipping_mode' => $itemData['shipping']['mode'] ?? '',
            ':logistic_type' => $itemData['shipping']['logistic_type'] ?? '',
            ':free_shipping' => ($itemData['shipping']['free_shipping'] ?? false) ? 1 : 0,
            ':date_created' => $itemData['date_created'] ?? date('Y-m-d H:i:s'),
        ]);
        
        $updated++;
    } catch (Exception $e) {
        logMsg("Error updating item $mlId: " . $e->getMessage());
    }
}

logMsg("Sync completed! Updated $updated items.");
logMsg("Total execution time: " . (time() - $START_TIME) . " seconds");
