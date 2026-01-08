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
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_INFILESIZE => -1, // Fix for some hosting envs
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

// Mock Access Token - In production this should come from DB/Auth
$access_token = getenv('MELI_ACCESS_TOKEN'); // OR fetch from 'accounts' table

if (!$access_token) {
    die("Access Token not found");
}

// 1. Check Lock (Simple file lock)
$lockFile = __DIR__ . '/sync.lock';
if (file_exists($lockFile) && (time() - filemtime($lockFile) < 60)) {
    die("Process already running.");
}
touch($lockFile);

try {
    // START LOOP
    while (time() - $START_TIME < $MAX_EXECUTION_TIME) {
        
        // --- PHASE 1: SCAN IDs (Simplified - Assume we fetch a page of items via search) ---
        // In a real full sync, we would use scroll_id. Here we just fetch items that need update.
        // For simplicity, let's assume we have a list of ML_IDs or we fetch them now.
        // Let's implement the logic to fetch items that are "old" in our DB or new ones.
        
        // This part would typically maintain state of 'scroll_id' in the DB.
        
        // For this implementation, let's process items already in DB that need details, 
        // OR fetch new IDs if DB is empty.
        
        // ... (Skipping full scan logic for brevity, focusing on the requested "Update" logic)
        
        // --- PHASE 2: PROCESSING (The Core Logic) ---
        // Get items that haven't been updated in the last hour
        $stmt = $pdo->prepare("SELECT ml_id FROM items WHERE updated_at < NOW() - INTERVAL '1 hour' ORDER BY updated_at ASC LIMIT :limit");
        $stmt->bindValue(':limit', $BATCH_SIZE, PDO::PARAM_INT);
        $stmt->execute();
        $idsToUpdate = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($idsToUpdate)) {
            // Nothing to update, maybe fetch new IDs from ML here?
            logMsg("No items to update. Sleeping...");
            sleep(5);
            continue;
        }

        $idsString = implode(',', $idsToUpdate);
        $itemsData = callMeliApi("/items?ids=$idsString", $access_token);

        if (!$itemsData) {
            logMsg("Failed to fetch items data.");
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
            
            // Extract 360 Logic (Logistics)
            $shipping = $body['shipping'] ?? [];
            $shipping_mode = $shipping['mode'] ?? 'not_specified';
            $logistic_type = $shipping['logistic_type'] ?? 'not_specified';
            $free_shipping = $shipping['free_shipping'] ?? false;
            $tags = json_encode($body['tags'] ?? []);

            // Analisador Logic (Last Sale Date)
            $last_sale_date = null;
            if ($sold_quantity > 0) {
                // Fetch Orders to get true last sale date
                // Note: /orders/search is restricted/deprecated in some contexts, but used as per requirements.
                // Modern API might differ, but implementing as requested.
                $ordersData = callMeliApi("/orders/search?item=$ml_id&limit=1&sort=date_desc", $access_token);
                if ($ordersData && !empty($ordersData['results'])) {
                    $last_sale_date = $ordersData['results'][0]['date_closed'];
                }
            }
            
            // Upsert into DB
            $sql = "INSERT INTO items (
                        ml_id, title, price, status, permalink, thumbnail, 
                        date_created, sold_quantity, 
                        shipping_mode, logistic_type, free_shipping, tags, 
                        last_sale_date, updated_at
                    ) VALUES (
                        :ml_id, :title, :price, :status, :permalink, :thumbnail,
                        :date_created, :sold_quantity,
                        :shipping_mode, :logistic_type, :free_shipping, :tags,
                        :last_sale_date, NOW()
                    ) ON CONFLICT (ml_id) DO UPDATE SET
                        title = EXCLUDED.title,
                        price = EXCLUDED.price,
                        status = EXCLUDED.status,
                        sold_quantity = EXCLUDED.sold_quantity,
                        shipping_mode = EXCLUDED.shipping_mode,
                        logistic_type = EXCLUDED.logistic_type,
                        last_sale_date = EXCLUDED.last_sale_date,
                        updated_at = NOW();";
            
            $upsert = $pdo->prepare($sql);
            $upsert->execute([
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
            
            logMsg("Updated $ml_id");
        }
        
        sleep(1); // Prevent API rate limits
    }
} catch (Exception $e) {
    logMsg("Error: " . $e->getMessage());
} finally {
    unlink($lockFile);
}
