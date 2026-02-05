<?php
// backend/auth/reset_password.php
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$input = json_decode(file_get_contents('php://input'), true);
$token = $input['token'] ?? '';
$password = $input['password'] ?? '';

if (!$token || strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Token inválido ou senha muito curta (mínimo 8 caracteres)']);
    exit;
}

try {
    $pdo = getDatabaseConnection();

    // Find user with valid token
    $stmt = $pdo->prepare("
        SELECT id FROM saas_users 
        WHERE reset_token = :token 
        AND reset_expires_at > NOW()
    ");
    $stmt->execute([':token' => $token]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Token inválido ou expirado']);
        exit;
    }

    // Update password and clear token
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $stmtUpdate = $pdo->prepare("
        UPDATE saas_users 
        SET password_hash = :pwd, 
            reset_token = NULL, 
            reset_expires_at = NULL 
        WHERE id = :id
    ");
    $stmtUpdate->execute([
        ':pwd' => $passwordHash,
        ':id' => $user['id']
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Senha redefinida com sucesso. Você já pode fazer login.'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()]);
}
