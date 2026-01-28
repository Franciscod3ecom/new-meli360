<?php
// backend/auth/callback.php
require_once __DIR__ . '/../config/database.php';

// Configuration
$config = require __DIR__ . '/../config/config.php';

$APP_ID = $config['MELI_APP_ID'];
$SECRET_KEY = $config['MELI_SECRET_KEY'];
$REDIRECT_URI = $config['MELI_REDIRECT_URI'];

if (!$APP_ID || !$SECRET_KEY || !$REDIRECT_URI) {
    die("Configuration Error: Missing credentials in config.php.");
}

$code = $_GET['code'] ?? null;

if (!$code) {
    die("Error: No code provided.");
}

// 1. Exchange Code for Token
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => "https://api.mercadolibre.com/oauth/token",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'grant_type' => 'authorization_code',
        'client_id' => $APP_ID,
        'client_secret' => $SECRET_KEY,
        'code' => $code,
        'redirect_uri' => $REDIRECT_URI
    ]),
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
]);

$response = curl_exec($curl);
$err = curl_error($curl);
curl_close($curl);

if ($err) {
    die("cURL Error: $err");
}

$data = json_decode($response, true);

if (isset($data['error'])) {
    die("ML Error: " . $data['error'] . " - " . $data['message']);
}

// 2. Save to Database
$access_token = $data['access_token'];
$refresh_token = $data['refresh_token'];
$expires_in = $data['expires_in'];
$user_id = $data['user_id'];
$expires_at = date('Y-m-d H:i:s', time() + $expires_in);

try {
    $pdo = getDatabaseConnection();

    // Get Nickname (Optional, but nice to have)
    // We can fetch user info with the new token
    $userCurl = curl_init("https://api.mercadolibre.com/users/me");
    curl_setopt($userCurl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($userCurl, CURLOPT_HTTPHEADER, ["Authorization: Bearer $access_token"]);
    $userInfoRaw = curl_exec($userCurl);
    curl_close($userCurl);
    $userInfo = json_decode($userInfoRaw, true);
    $nickname = $userInfo['nickname'] ?? 'Unknown';

    $sql = "INSERT INTO accounts (ml_user_id, nickname, access_token, refresh_token, expires_at, updated_at)
            VALUES (:ml_id, :nickname, :access_token, :refresh_token, :expires_at, NOW())
            ON CONFLICT (ml_user_id) DO UPDATE SET
                nickname = EXCLUDED.nickname,
                access_token = EXCLUDED.access_token,
                refresh_token = EXCLUDED.refresh_token,
                expires_at = EXCLUDED.expires_at,
                updated_at = NOW()";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':ml_id' => $user_id,
        ':nickname' => $nickname,
        ':access_token' => $access_token,
        ':refresh_token' => $refresh_token,
        ':expires_at' => $expires_at
    ]);

    // 3. Start Session and Store Data
    session_start();
    $_SESSION['user_id'] = $user_id;
    $_SESSION['nickname'] = $nickname;
    $_SESSION['access_token'] = $access_token;
    $_SESSION['user_type'] = 'oauth'; // IMPORTANT: Set user_type to oauth so me.php knows how to handle it

    // 4. Redirect to Frontend
    $FRONTEND_URL = $config['FRONTEND_URL'] ?: 'http://localhost:5173';
    header("Location: $FRONTEND_URL?auth_success=true");
    exit;

} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}
