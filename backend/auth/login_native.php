<?php
/**
 * Arquivo: login_native.php
 * Descrição: API para login com e-mail e senha
 */

session_start();

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';

// Ler entrada JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Dados inválidos']);
    exit;
}

$email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
$password = $input['password'] ?? '';

// DEBUG: Log incoming data
file_put_contents(
    __DIR__ . '/../login_debug.log',
    date('Y-m-d H:i:s') . " - Email recebido: [" . ($input['email'] ?? 'VAZIO') . "] | Senha: [" . ($input['password'] ?? 'VAZIO') . "]\n",
    FILE_APPEND
);

if (!$email || !$password) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'E-mail e senha são obrigatórios']);
    exit;
}

try {
    $pdo = getDatabaseConnection();

    // Buscar usuário na tabela correta (saas_users)
    $stmt = $pdo->prepare("SELECT id, email, password_hash FROM saas_users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // DEBUG: Log user lookup result
    file_put_contents(
        __DIR__ . '/../login_debug.log',
        "Usuário encontrado: " . ($user ? "SIM (ID: {$user['id']})" : "NÃO") . "\n",
        FILE_APPEND
    );

    if ($user && password_verify($password, $user['password_hash'])) {
        // DEBUG: Log success
        file_put_contents(
            __DIR__ . '/../login_debug.log',
            "✅ Password verify: SUCESSO!\n",
            FILE_APPEND
        );

        // Login com sucesso
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['user_type'] = 'native'; // Differentiate from OAuth login

        // DEBUG: Log before response
        file_put_contents(
            __DIR__ . '/../login_debug.log',
            "📤 Enviando resposta de sucesso (200 OK)\n",
            FILE_APPEND
        );

        echo json_encode([
            'success' => true,
            'message' => 'Login realizado com sucesso',
            'user' => [
                'id' => $user['id'],
                'email' => $user['email']
            ]
        ]);
        exit;
    } else {
        // DEBUG: Log failure details
        $verify_result = $user ? password_verify($password, $user['password_hash']) : 'N/A';
        file_put_contents(
            __DIR__ . '/../login_debug.log',
            "❌ Password verify: " . ($verify_result ? "TRUE" : "FALSE") . " | User exists: " . ($user ? "YES" : "NO") . "\n",
            FILE_APPEND
        );

        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'E-mail ou senha incorretos']);
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno ao realizar login: ' . $e->getMessage()]);
}
