<?php
// backend/cron/sync_debug_v3.php
// DEBUG COM REFRESH TOKEN AUTOMÁTICO
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain; charset=utf-8');

session_start();
require_once __DIR__ . '/../config/database.php';

// Load Config for Client ID/Secret
$config = require __DIR__ . '/../config/config.php';
$APP_ID = $config['MELI_APP_ID'];
$SECRET_KEY = $config['MELI_SECRET_KEY'];

echo "=== SYNC DEBUG V3 (AUTO REFRESH) ===\n\n";

if (!isset($_SESSION['user_id'])) {
    die("ERRO: Sessão perdida. Faça login novamente.\n");
}
$ml_user_id = $_SESSION['user_id'];
echo "Usuário ID: $ml_user_id\n";

// 1. Get Current Token
$pdo = getDatabaseConnection();
$stmt = $pdo->prepare("SELECT * FROM accounts WHERE ml_user_id = :id LIMIT 1");
$stmt->execute([':id' => $ml_user_id]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$account) die("Conta não encontrada no DB.\n");

$access_token = $account['access_token'];
$refresh_token = $account['refresh_token'];
echo "Token atual: " . substr($access_token, 0, 10) . "...\n";

// Function to Refresh Token
function refreshToken($pdo, $refresh_token, $app_id, $secret, $ml_user_id) {
    echo "\n>>> REFRESHING TOKEN...\n";
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
        echo "[SUCESSO] Novo token gerado!\n";
        
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
        echo "[ERRO REFRESH] " . ($data['message'] ?? 'Unknown error') . "\n";
        return false;
    }
}

// 2. Test Search (With Retry Logic)
echo "\n>>> Testando Busca (/users/$ml_user_id/items/search)...\n";

$url = "https://api.mercadolibre.com/users/$ml_user_id/items/search?status=active&limit=5";
function tryRequest($url, $token) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => $res];
}

$response = tryRequest($url, $access_token);
echo "HTTP Code: " . $response['code'] . "\n";

// 3. Handle 401 (Refresh)
if ($response['code'] == 401) {
    echo "[401 DETECTADO] Token expirado. Tentando refresh automático...\n";
    $new_token = refreshToken($pdo, $refresh_token, $APP_ID, $SECRET_KEY, $ml_user_id);
    
    if ($new_token) {
        $response = tryRequest($url, $new_token);
        echo "NOVO HTTP Code: " . $response['code'] . "\n";
        echo "Response Body Sample: " . substr($response['body'], 0, 100) . "...\n";
        
        $data = json_decode($response['body'], true);
        echo "Total de itens encontrados: " . ($data['paging']['total'] ?? 0) . "\n";
    } else {
        echo "Falha crítica: Não foi possível renovar o token. Faça login novamente.\n";
    }
} else {
    echo "Sucesso direto.\n";
    echo "Body: " . substr($response['body'], 0, 100) . "...\n";
}
