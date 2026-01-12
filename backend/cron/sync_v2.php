<?php
// backend/cron/sync_v2.php
// Ported from Analisador v6.2 logic

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/ml_api.php'; // Ensure this has basic helpers

$MAX_EXECUTION_TIME = 50; // Keep slightly below Hostinger generic 60s limit
$START_TIME = time();

define('API_CALL_DELAY_MS', 250);
define('SCROLL_PAGE_SIZE', 50);
define('MAX_SCROLL_ITERATIONS', 50); // Limit per run to avoid timeouts

function logMsgV2($msg) {
    echo "[" . date('Y-m-d H:i:s') . "] $msg\n";
}

// Ensure lock
$lockFile = __DIR__ . '/sync_v2.lock';
if (file_exists($lockFile) && (time() - filemtime($lockFile) < 120)) {
    die("Process already running (Lockfile).");
}
touch($lockFile);

try {
    $pdo = getDatabaseConnection();
    
    // 1. Get Account to Sync
    // Logic: Find account manually requested OR syncing OR idle sorted by last run
    $stmt = $pdo->query("SELECT * FROM accounts 
                         ORDER BY 
                            CASE WHEN sync_status = 'REQUESTED' THEN 0 
                                 WHEN sync_status = 'SYNCING' THEN 1 
                                 ELSE 2 END ASC,
                            sync_last_run_at ASC NULLS FIRST
                         LIMIT 1");
    $account = $stmt->fetch();
    
    if (!$account) {
        throw new Exception("No accounts found.");
    }
    
    $accountId = $account['id'];
    $mlUserId = $account['ml_user_id'];
    $accessToken = $account['access_token'];
    $refreshToken = $account['refresh_token'];
    $expiresAt = strtotime($account['expires_at']);
    $syncStatus = $account['sync_status'] ?? 'IDLE';
    $scrollId = $account['sync_scroll_id'];
    
    logMsgV2("--> [ML $mlUserId] Starting Sync V2. Status: $syncStatus");
    
    // Update status to SYNCING
    $pdo->prepare("UPDATE accounts SET sync_status = 'SYNCING', sync_last_run_at = NOW() WHERE id = ?")->execute([$accountId]);
    
    // 2. Token Refresh Check
    if (time() >= ($expiresAt - 600)) { // 10 mins buffer
        logMsgV2("    Token expiring. Refreshing...");
        // Call ML Token Endpoint (Manual CURL here since mapped in ml_api might differ slightly)
        // Adjust config based on your environment vars
        $config = require __DIR__ . '/../config/config.php';
        $appId = $config['MELI_APP_ID'];
        $secret = $config['MELI_SECRET_KEY'];
        
        $refreshRes = makeCurlRequest('https://api.mercadolibre.com/oauth/token', 'POST', [
            'Accept: application/json', 
            'Content-Type: application/x-www-form-urlencoded'
        ], [
            'grant_type' => 'refresh_token',
            'client_id' => $appId,
            'client_secret' => $secret,
            'refresh_token' => $refreshToken
        ], false); // Form encoded
        
        if ($refreshRes['httpCode'] == 200 && isset($refreshRes['response']['access_token'])) {
            $data = $refreshRes['response'];
            $accessToken = $data['access_token'];
            $refreshToken = $data['refresh_token']; // Rotate refresh token
            $newExpiresAt = date('Y-m-d H:i:s', time() + $data['expires_in']);
            
            $pdo->prepare("UPDATE accounts SET access_token = ?, refresh_token = ?, expires_at = ? WHERE id = ?")
                ->execute([$accessToken, $refreshToken, $newExpiresAt, $accountId]);
            logMsgV2("    Token Refreshed.");
        } else {
             throw new Exception("Token Refresh Failed: " . json_encode($refreshRes));
        }
    }
    
    $headers = ['Authorization: Bearer ' . $accessToken];
    
    // --- PHASE 1: DISCOVERY (SCROLL) ---
    // If we have a scroll_id OR if status was REQUESTED (implies restart), we iterate
    
    $isDiscovery = ($syncStatus === 'REQUESTED' || !empty($scrollId));
    
    if ($isDiscovery) {
        if ($syncStatus === 'REQUESTED') {
            logMsgV2("    Resetting sync state (REQUESTED)...");
            // Optional: Mark all local items as dirty/removed? Or just rely on insert ignore.
            // For now, we just clear scroll_id to start fresh.
            $pdo->prepare("UPDATE accounts SET sync_scroll_id = NULL WHERE id = ?")->execute([$accountId]);
            $scrollId = null; 
        }

        $iter = 0;
        while ($iter < MAX_SCROLL_ITERATIONS) {
            if ((time() - $START_TIME) > ($MAX_EXECUTION_TIME - 10)) {
                logMsgV2("    Time limit reached during discovery.");
                break;
            }
            
            $url = "https://api.mercadolibre.com/users/$mlUserId/items/search?search_type=scan&limit=" . SCROLL_PAGE_SIZE;
            if ($scrollId) {
                $url .= "&scroll_id=" . urlencode($scrollId);
            }
            
            $res = makeCurlRequest($url, 'GET', $headers);
            
            if ($res['httpCode'] != 200) {
                 // If scroll_id expired or error, reset
                 if ($res['httpCode'] == 400 || $res['httpCode'] == 404) {
                     logMsgV2("    Scroll ID invalid/expired. Resetting scan.");
                     $pdo->prepare("UPDATE accounts SET sync_scroll_id = NULL WHERE id = ?")->execute([$accountId]);
                     $scrollId = null;
                     continue; 
                 }
                 throw new Exception("API Error Scan: " . $res['httpCode']);
            }
            
            $results = $res['response']['results'] ?? [];
            $newScrollId = $res['response']['scroll_id'] ?? null;
            $scrollId = $newScrollId; // Update local var
            
            if (empty($results)) {
                logMsgV2("    Scan Completed (End of results).");
                $pdo->prepare("UPDATE accounts SET sync_scroll_id = NULL, sync_last_message = 'Discovery Complete' WHERE id = ?")->execute([$accountId]);
                $isDiscovery = false; // Move to detail phase
                break;
            }
            
            // Insert IDs
            $placeholders = [];
            $params = [];
            foreach ($results as $mlId) {
                $placeholders[] = "(?, ?, 0)"; // 0 = Pending Detail
                array_push($params, $mlId, $accountId);
            }
            
            if (!empty($placeholders)) {
                // Postgres/Supabase syntax: ON CONFLICT DO NOTHING
                // We use is_synced=0 to mark it needs update if it didn't exist
                $sqlVals = implode(',', $placeholders);
                $sql = "INSERT INTO items (ml_id, account_id, is_synced) VALUES $sqlVals ON CONFLICT (ml_id) DO NOTHING"; 
                // Note: If item exists, we don't force is_synced=0 here to avoid re-syncing constantly unless necessary. 
                // Ideally, we'd update is_synced=0 if we wanted to force update.
                $pdo->prepare($sql)->execute($params);
            }
            
            // Save Checkpoint
            $pdo->prepare("UPDATE accounts SET sync_scroll_id = ? WHERE id = ?")->execute([$scrollId, $accountId]);
            
            $iter++;
            usleep(API_CALL_DELAY_MS * 1000);
        }
    }
    
    // --- PHASE 2: DETAILING ---
    // If not in discovery mode (or if we have time left), process "dirty" items (is_synced = 0)
    
    if (!$isDiscovery && (time() - $START_TIME < $MAX_EXECUTION_TIME)) {
        
        $limit = 50; // Batch for detailed info
        // Select items that are pending sync (0) or requested update (2)
        $stmtDetails = $pdo->prepare("SELECT ml_id FROM items WHERE account_id = ? AND is_synced IN (0, 2) LIMIT $limit");
        $stmtDetails->execute([$accountId]);
        $idsToDetail = $stmtDetails->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($idsToDetail)) {
            logMsgV2("    No items pending detail. Sync Cycle Complete.");
            $pdo->prepare("UPDATE accounts SET sync_status = 'COMPLETED' WHERE id = ?")->execute([$accountId]);
        } else {
            logMsgV2("    Detailing " . count($idsToDetail) . " items...");
            
            // Multiget
            $idsStr = implode(',', $idsToDetail);
            $resItems = makeCurlRequest("https://api.mercadolibre.com/items?ids=$idsStr", 'GET', $headers);
            
            if ($resItems['httpCode'] == 200 && is_array($resItems['response'])) {
                foreach ($resItems['response'] as $iWrapper) {
                    if ($iWrapper['code'] != 200) continue;
                    $body = $iWrapper['body'];
                    
                    $mlId = $body['id'];
                    $sold = $body['sold_quantity'];
                    
                    // Fetch Visits (Multi-get not always reliable for visits, doing single for robustness per legacy script)
                    // Or skip to save calls if rate limited. Legacy script did single calls.
                    $visits = 0;
                    $resVisits = makeCurlRequest("https://api.mercadolibre.com/visits/items?ids=$mlId", 'GET', $headers);
                    if ($resVisits['httpCode'] == 200) {
                         $visits = $resVisits['response'][$mlId] ?? 0;
                    }
                    
                    // Fetch Last Sale
                    $lastSale = null;
                    if ($sold > 0) {
                         $resOrders = makeCurlRequest("https://api.mercadolibre.com/orders/search?seller=$mlUserId&item=$mlId&sort=date_desc&limit=1", 'GET', $headers);
                         if ($resOrders['httpCode'] == 200 && !empty($resOrders['response']['results'])) {
                             $lastSale = $resOrders['response']['results'][0]['date_closed'];
                         }
                    }
                    
                    // Update DB
                    // Parsing dates
                    $dateCreated = $body['date_created'];
                    
                    $sqlUp = "UPDATE items SET 
                              title = ?, price = ?, status = ?, permalink = ?, thumbnail = ?, 
                              sold_quantity = ?, available_quantity = ?, date_created = ?,
                              shipping_mode = ?, logistic_type = ?, free_shipping = ?,
                              total_visits = ?, last_sale_date = ?, 
                              is_synced = 1, updated_at = NOW()
                              WHERE ml_id = ?";
                              
                    $pdo->prepare($sqlUp)->execute([
                        $body['title'], $body['price'], $body['status'], $body['permalink'], $body['thumbnail'],
                        $sold, $body['available_quantity'], $dateCreated,
                        $body['shipping']['mode'] ?? 'not_specified', $body['shipping']['logistic_type'] ?? 'not_specified', ($body['shipping']['free_shipping'] ?? false) ? 'true' : 'false',
                        $visits, $lastSale,
                        $mlId
                    ]);
                }
            }
        }
    }

} catch (Exception $e) {
    logMsgV2("ERROR: " . $e->getMessage());
    if (isset($pdo) && isset($accountId)) {
         $pdo->prepare("UPDATE accounts SET sync_last_message = ? WHERE id = ?")->execute(['Error: ' . $e->getMessage(), $accountId]);
    }
} finally {
    if (file_exists($lockFile)) unlink($lockFile);
}
