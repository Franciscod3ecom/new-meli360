<?php
/**
 * Arquivo: change_password.php
 * Descrição: API para alterar a senha do usuário logado.
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

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'native') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Acesso negado. Faça login para alterar a senha.']);
    exit;
}

// Ler entrada JSON
$input = json_decode(file_get_contents('php://input'), true);
$currentPassword = $input['current_password'] ?? '';
$newPassword = $input['new_password'] ?? '';

if (!$currentPassword || !$newPassword) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Senha atual e nova senha são obrigatórias']);
    exit;
}

if (strlen($newPassword) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'A nova senha deve ter pelo menos 6 caracteres']);
    exit;
}

try {
    $pdo = getDatabaseConnection();

    // 1. Buscar o hash atual do usuário
    $stmt = $pdo->prepare("SELECT id, password_hash FROM saas_users WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Senha atual incorreta']);
        exit;
    }

    // 2. Atualizar para a nova senha
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmtUpdate = $pdo->prepare("UPDATE saas_users SET password_hash = :pwd, updated_at = NOW() WHERE id = :id");
    $stmtUpdate->execute([
        ':pwd' => $newHash,
        ':id' => $user['id']
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Senha alterada com sucesso!'
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno ao alterar senha: ' . $e->getMessage()]);
}
