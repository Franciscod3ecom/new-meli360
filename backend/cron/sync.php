<?php
// backend/cron/sync.php
require_once __DIR__ . '/../config/database.php';

// Configuration
$MAX_EXECUTION_TIME = 50; // Seconds
$BATCH_SIZE = 50;
$START_TIME = time();

// --- Helper Functions ---

function logMsg($msg) {
    echo "[" . date('Y-m-d H:i:s') . "] $msg\n";
}

function callMeliApi($endpoint, $token) {
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api.mercadolibre.com$endpoint",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30, // Increased timeout
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $token"
        ],
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    
    if ($err) {
        logMsg("cURL Error: $err");
        return null;
    }
    return json_decode($response, true);
}

// --- Main Logic ---

$pdo = getDatabaseConnection();

// 1. Fetch Account & Valid Token
// For simplicity, we get the first account. In multi-tenant, we'd loop through accounts.
$stmt = $pdo->query("SELECT * FROM accounts LIMIT 1");
$account = $stmt->fetch();

if (!$account) {
    die("No accounts found. Please login via /auth/login.php first.");
}

// TODO: Implement Token Refresh if expired
// if (strtotime($account['expires_at']) < time()) { ... refresh logic ... }

$access_token = $account['access_token'];
$account_id = $account['id']; // UUID from our DB
$ml_user_id = $account['ml_user_id'];

// 2. Check Lock
$lockFile = __DIR__ . '/sync.lock';
if (file_exists($lockFile) && (time() - filemtime($lockFile) < 120)) { // 2 mins lock
    die("Process already running.");
}
touch($lockFile);

try {
    // START LOOP
    while (time() - $START_TIME < $MAX_EXECUTION_TIME) {
        
        // --- PHASE 1: SCAN IDs (Discovery) ---
        // Strategy: We check if we have roughly the correct number of items.
        // If our DB count is significantly different from ML total, we trigger a scan.
        
        // 1. Get Total Items on ML
        $searchData = callMeliApi("/users/$ml_user_id/items/search?status=active&limit=1", $access_token);
        $totalRemote = $searchData['paging']['total'] ?? 0;
        
        $stmtCount = $pdo->query("SELECT COUNT(*) FROM items");
        $totalLocal = $stmtCount->fetchColumn();
        
        // Determine if we need to scan (e.g., if local < remote)
        // Note: Ideally use a flag or a separate cron for full scan, but here we do a 'light' check
        if ($totalLocal < $totalRemote) {
            logMsg("Scan needed: Local($totalLocal) vs Remote($totalRemote)");
            
            // Perform Scan using Search
            // NOTE: 'search_type=scan' is deprecated in favor of scroll_id, but standard search with limit=50 and offset works for smaller catalogs.
            // For robust large catalogs, use scroll_id. Implementing standard pagination for simplicity and broad compatibility here.
            
            $offset = 0;
            while (true) {
                if (time() - $START_TIME > ($MAX_EXECUTION_TIME - 10)) break; // Safety break
                
                $url = "/users/$ml_user_id/items/search?status=active&limit=50&offset=$offset";
                $res = callMeliApi($url, $access_token);
                
                if (empty($res['results'])) break;
                
                $mappedIds = [];
                foreach ($res['results'] as $ml_id) {
                    $mappedIds[] = "('$ml_id', '$account_id')";
                }
                
                if (!empty($mappedIds)) {
                    $valuesSql = implode(',', $mappedIds);
                    // Bulk Insert Ignore (Postgres approach: ON CONFLICT DO NOTHING)
                    $sql = "INSERT INTO items (ml_id, account_id) VALUES $valuesSql ON CONFLICT (ml_id) DO NOTHING";
                    $pdo->exec($sql);
                    logMsg("Scanned/Inserted batch at offset $offset");
                }
                
                $offset += 50;
                if ($offset >= $res['paging']['total']) break;
                
                sleep(1);
            }
        }
        
        
        // --- PHASE 2: PROCESSING (Enrichment) ---
        // Get items that need update (oldest updated_at OR null fields)
        // We prioritize items with null title (newly scanned)
        $stmt = $pdo->prepare("SELECT ml_id FROM items WHERE title IS NULL OR updated_at < NOW() - INTERVAL '1 hour' ORDER BY title ASC NULLS FIRST, updated_at ASC LIMIT :limit");
        $stmt->bindValue(':limit', $BATCH_SIZE, PDO::PARAM_INT);
        $stmt->execute();
        $idsToUpdate = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($idsToUpdate)) {
            logMsg("All items up to date. Sleeping...");
            sleep(5);
            // Optionally break if really nothing to do
            // break;
            continue;
        }

        $idsString = implode(',', $idsToUpdate);
        $itemsData = callMeliApi("/items?ids=$idsString", $access_token);

        if (!$itemsData) {
            logMsg("Failed to fetch items data details.");
            break;
        }

        foreach ($itemsData as $itemWrapper) {
            $code = $itemWrapper['code'];
            $body = $itemWrapper['body'];
            
            if ($code != 200) continue;

            $ml_id = $body['id'];
            $title = $body['title'];
            $price = $body['price'];
            $permalink = $body['permalink'];
            $thumbnail = $body['thumbnail'];
            $status = $body['status'];
            $date_created = $body['date_created'];
            $sold_quantity = $body['sold_quantity'];
            
            // Extract 360 Logic
            $shipping = $body['shipping'] ?? [];
            $shipping_mode = $shipping['mode'] ?? 'not_specified';
            $logistic_type = $shipping['logistic_type'] ?? 'not_specified';
            $free_shipping = $shipping['free_shipping'] ?? false;
            $tags = json_encode($body['tags'] ?? []);

            // Analisador Logic (Last Sale)
            $last_sale_date = null;
            if ($sold_quantity > 0) {
                // Try to get last sale date
                $ordersData = callMeliApi("/orders/search?item=$ml_id&limit=1&sort=date_desc", $access_token);
                if ($ordersData && !empty($ordersData['results'])) {
                    $last_sale_date = $ordersData['results'][0]['date_closed'];
                }
            }
            
            // Upsert
            $sql = "UPDATE items SET
                        title = :title,
                        price = :price,
                        status = :status,
                        permalink = :permalink,
                        thumbnail = :thumbnail,
                        date_created = :date_created,
                        sold_quantity = :sold_quantity,
                        shipping_mode = :shipping_mode,
                        logistic_type = :logistic_type,
                        free_shipping = :free_shipping,
                        tags = :tags,
                        last_sale_date = :last_sale_date,
                        updated_at = NOW()
                    WHERE ml_id = :ml_id";
            
            $update = $pdo->prepare($sql);
            $update->execute([
                ':ml_id' => $ml_id,
                ':title' => $title,
                ':price' => $price,
                ':status' => $status,
                ':permalink' => $permalink,
                ':thumbnail' => $thumbnail,
                ':date_created' => $date_created,
                ':sold_quantity' => $sold_quantity,
                ':shipping_mode' => $shipping_mode,
                ':logistic_type' => $logistic_type,
                ':free_shipping' => $free_shipping ? 'true' : 'false',
                ':tags' => $tags,
                ':last_sale_date' => $last_sale_date
            ]);
            
            logMsg("Updated details for $ml_id");
        }
        
        sleep(1);
    }
} catch (Exception $e) {
    logMsg("Error: " . $e->getMessage());
} finally {
    if (file_exists($lockFile)) unlink($lockFile);
}
