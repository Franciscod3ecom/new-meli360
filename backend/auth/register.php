<?php
/**
 * Arquivo: register.php
 * Descrição: API para cadastro de novos usuários
 */

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

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
$confirmPassword = $input['confirm_password'] ?? '';

// Validações
if (!$email) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'E-mail inválido']);
    exit;
}

if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'A senha deve ter no mínimo 8 caracteres']);
    exit;
}

if ($password !== $confirmPassword) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'As senhas não coincidem']);
    exit;
}

try {
    $pdo = getDatabaseConnection();

    // Verificar se já existe
    $stmt = $pdo->prepare("SELECT id FROM saas_users WHERE email = :email");
    $stmt->execute([':email' => $email]);

    if ($stmt->fetch()) {
        http_response_code(409); // Conflict
        echo json_encode(['success' => false, 'error' => 'Este e-mail já está em uso']);
        exit;
    }

    // Criar usuário
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $stmtInsert = $pdo->prepare("
        INSERT INTO saas_users (email, password_hash, created_at, updated_at) 
        VALUES (:email, :pwd, NOW(), NOW())
    ");

    $stmtInsert->execute([':email' => $email, ':pwd' => $passwordHash]);
    $userId = $pdo->lastInsertId();

    // Auto-login (iniciar sessão)
    session_start();
    $_SESSION['user_id'] = $userId;
    $_SESSION['email'] = $email;

    echo json_encode([
        'success' => true,
        'message' => 'Conta criada com sucesso',
        'user' => [
            'id' => $userId,
            'email' => $email
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno ao criar conta: ' . $e->getMessage()]);
}
