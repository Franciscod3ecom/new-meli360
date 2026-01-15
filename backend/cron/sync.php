<?php
// backend/cron/sync.php
// PRODUCTION VERSION WITH AUTO REFRESH
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain; charset=utf-8');

session_start();

require_once __DIR__ . '/../config/database.php';
// Load Config for Client ID/Secret
$config = require __DIR__ . '/../config/config.php';
$APP_ID = $config['MELI_APP_ID'];
$SECRET_KEY = $config['MELI_SECRET_KEY'];

// Configuration
$MAX_EXECUTION_TIME = 60; // Increased
$BATCH_SIZE = 50;
$START_TIME = time();

function logMsg($msg) {
    echo "[" . date('Y-m-d H:i:s') . "] $msg\n";
    flush();
}

function refreshToken($pdo, $refresh_token, $app_id, $secret, $ml_user_id) {
    logMsg("Token expired (401). Attempting refresh...");
    $ch = curl_init("https://api.mercadolibre.com/oauth/token");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'refresh_token',
        'client_id' => $app_id,
        'client_secret' => $secret,
        'refresh_token' => $refresh_token
    ]));
    
    $response = curl_exec($ch);
    $data = json_decode($response, true);
    curl_close($ch);
    
    if (isset($data['access_token'])) {
        logMsg("Success! New token generated.");
        
        // Update DB
        $new_access = $data['access_token'];
        $new_refresh = $data['refresh_token'];
        $expires_in = $data['expires_in'];
        $expires_at = date('Y-m-d H:i:s', time() + $expires_in);
        
        $sql = "UPDATE accounts SET access_token = :access, refresh_token = :refresh, expires_at = :expires, updated_at = NOW() WHERE ml_user_id = :user_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':access' => $new_access, 
            ':refresh' => $new_refresh, 
            ':expires' => $expires_at, 
            ':user_id' => $ml_user_id
        ]);
        
        return $new_access;
    } else {
        logMsg("REFRESH ERROR: " . ($data['message'] ?? 'Unknown error'));
        return false;
    }
}

function callMeliApi($endpoint, $token) {
    global $pdo, $refresh_token, $APP_ID, $SECRET_KEY, $ml_user_id;

    $url = "https://api.mercadolibre.com$endpoint";
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer $token"],
    ]);
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    // Initial check for 401
    if ($httpCode === 401) {
        $newToken = refreshToken($pdo, $refresh_token, $APP_ID, $SECRET_KEY, $ml_user_id);
        if ($newToken) {
            // Update global token and retry
            // NOTE: Logic here relies on caller updating their reference or us doing a recursive call with new token.
            // Simplified: Recursive call with new token?
            // Actually, we need to update the token variable in the main scope for subsequent calls in the loop
            // But for this specific call, let's retry.
            
            logMsg("Retrying request with new token...");
            $curl2 = curl_init();
            curl_setopt_array($curl2, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => ["Authorization: Bearer $newToken"],
            ]);
            $response = curl_exec($curl2);
            $httpCode = curl_getinfo($curl2, CURLINFO_HTTP_CODE);
            curl_close($curl2);
            
            // If still fails, return null
            if ($httpCode !== 200) {
                 logMsg("API Error after refresh: HTTP $httpCode - $endpoint");
                 return null;
            }
            return json_decode($response, true);
        } else {
             logMsg("Failed to refresh token. Aborting.");
             return null;
        }
    }
    
    if ($httpCode !== 200) {
        logMsg("API Error: HTTP $httpCode - $endpoint");
        return null;
    }
    
    return json_decode($response, true);
}

// --- MAIN LOGIC ---

logMsg("Starting sync v3 (Auto-Refresh + Metrics)...");

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
        die("ERROR: Account not found\n");
    }
    
    $account_id = $account['id'];
    $access_token = $account['access_token'];
    $refresh_token = $account['refresh_token']; // Needed for refresh
    logMsg("Account found: $account_id");
    
} catch (Exception $e) {
    die("DB ERROR: " . $e->getMessage() . "\n");
}

// 3. Get total items - FIRST CALL (May trigger refresh)
logMsg("Fetching items...");
// Special handling for first call to update $access_token in main scope if refreshed
// We'll wrap callMeliApi to handle the retry internally, but we need to ensure loop uses new token.
// The callMeliApi above returns the data, but doesn't update $access_token variable in this scope easily without pass-by-ref or globals.
// Let's rely on the DB being updated. If 401 happens in loop, we should probably re-fetch token from DB or return it.
// Simpler approach: callMeliApi returns [data, newToken?] NO.
// Let's make callMeliApi robust enough to just work. 
// Ideally, we fetch items. If it initiates a refresh, it updates DB.
// Subsequent calls might fail with old memory token? Yes.
// We need to reload token from DB if a refresh happened? Or callMeliApi explicitly handles the retry.

// RE-IMPLEMENTING callMeliApi to be simpler and update the token via reference or just handle the single request
// Modification: We will implement a `requestWithRetry` function that uses global $access_token
function requestWithRetry($endpoint) {
    global $access_token, $refresh_token, $pdo, $APP_ID, $SECRET_KEY, $ml_user_id;
    return callMeliApi($endpoint, $access_token); // This uses the global vars logic inside callMeliApi which RECURSES on 401
    // Wait, the callMeliApi above DOES recurse.
    // The issue is: subsequent calls in the loop will still pass the OLD $access_token.
    // FIX: callMeliApi should accept token by reference OR we refresh the variable from DB if we detect a change?
    // Let's modify callMeliApi to return the new token? No, too complex.
    
    // BETTER STRATEGY: 
    // Just fetch items first. 
    // If we detect a refresh inside callMeliApi, we should update the global $access_token.
}

// REDEFINING callMeliApi for this script scope
function safeApiCall($endpoint) {
    global $access_token, $refresh_token, $pdo, $APP_ID, $SECRET_KEY, $ml_user_id;
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api.mercadolibre.com$endpoint",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer $access_token"],
    ]);
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    if ($httpCode === 401) {
        $newToken = refreshToken($pdo, $refresh_token, $APP_ID, $SECRET_KEY, $ml_user_id);
        if ($newToken) {
            $access_token = $newToken; // UPDATE GLOBAL VARIABLE
            
            // Retry
            $curl2 = curl_init();
            curl_setopt_array($curl2, [
                CURLOPT_URL => "https://api.mercadolibre.com$endpoint",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => ["Authorization: Bearer $access_token"],
            ]);
            $response = curl_exec($curl2);
            $httpCode = curl_getinfo($curl2, CURLINFO_HTTP_CODE);
            curl_close($curl2);
        } else {
             return null;
        }
    }
    
    if ($httpCode !== 200) {
        logMsg("API Error: HTTP $httpCode - $endpoint");
        return null;
    }
    
    return json_decode($response, true);
}


$searchData = safeApiCall("/users/$ml_user_id/items/search?status=active&limit=50");

if (!$searchData) die("ERROR: Failed to fetch items\n");

$itemIds = $searchData['results'] ?? [];
logMsg("Processing batch of " . count($itemIds) . " items...");

// 4. Fetch Details
$updated = 0;
foreach ($itemIds as $mlId) {
    if (time() - $START_TIME > $MAX_EXECUTION_TIME) break;
    
    // Fetch FULL item details using SAFE CALL (auto-updates token if needed)
    $item = safeApiCall("/items/$mlId?include_attributes=all");
    if (!$item) continue;
    
    try {
        // --- 1. High Res Image Logic ---
        $secure_thumbnail = $item['thumbnail'];
        if (!empty($item['pictures']) && is_array($item['pictures'])) {
            $firstPic = $item['pictures'][0];
            if (isset($firstPic['secure_url'])) {
                $secure_thumbnail = $firstPic['secure_url'];
            } elseif (isset($firstPic['url'])) {
                $secure_thumbnail = $firstPic['url'];
            }
        }
        
        // --- 2. Advanced Metrics ---
        $price = $item['price'];
        $original_price = $item['original_price'] ?? null;
        $currency_id = $item['currency_id'] ?? 'BRL';
        $health = $item['health'] ?? 0.00;
        
        // Upsert
        $sql = "INSERT INTO items (
            account_id, ml_id, title, price, status, permalink, thumbnail,
            sold_quantity, available_quantity, shipping_mode, logistic_type,
            free_shipping, date_created, updated_at,
            secure_thumbnail, health, original_price, currency_id
        ) VALUES (
            :account_id, :ml_id, :title, :price, :status, :permalink, :thumbnail,
            :sold_quantity, :available_quantity, :shipping_mode, :logistic_type,
            :free_shipping, :date_created, NOW(),
            :secure_thumbnail, :health, :original_price, :currency_id
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
            updated_at = NOW()";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':account_id' => $account_id,
            ':ml_id' => $item['id'],
            ':title' => $item['title'],
            ':price' => $price,
            ':status' => $item['status'],
            ':permalink' => $item['permalink'],
            ':thumbnail' => $item['thumbnail'],
            ':sold_quantity' => $item['sold_quantity'],
            ':available_quantity' => $item['available_quantity'],
            ':shipping_mode' => $item['shipping']['mode'] ?? '',
            ':logistic_type' => $item['shipping']['logistic_type'] ?? '',
            ':free_shipping' => ($item['shipping']['free_shipping'] ?? false) ? 1 : 0,
            ':date_created' => $item['date_created'],
            ':secure_thumbnail' => $secure_thumbnail,
            ':health' => $health,
            ':original_price' => $original_price,
            ':currency_id' => $currency_id
        ]);
        
        $updated++;
    } catch (Exception $e) {
        logMsg("Error updating $mlId: " . $e->getMessage());
    }
}

logMsg("Sync v3 completed! Updated $updated items.");
