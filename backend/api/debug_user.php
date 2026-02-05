<?php
// backend/api/debug_user.php
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

$email = $_GET['email'] ?? 'brunarafameli@gmail.com';

try {
    $pdo = getDatabaseConnection();
    echo "=== DEBUG USER: $email ===\n\n";

    $stmt = $pdo->prepare("SELECT id, email, password_hash FROM saas_users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo "Usuário NÃO encontrado na tabela 'saas_users'.\n";
    } else {
        echo "Usuário encontrado!\n";
        echo "ID: " . $user['id'] . "\n";
        echo "Email: " . $user['email'] . "\n";
        echo "Hash: " . $user['password_hash'] . "\n";

        $defaultPassword = 'meli360_default_password';
        $isValid = password_verify($defaultPassword, $user['password_hash']);

        echo "Verificação com senha padrão: " . ($isValid ? "✅ VÁLIDA" : "❌ INVÁLIDA") . "\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
