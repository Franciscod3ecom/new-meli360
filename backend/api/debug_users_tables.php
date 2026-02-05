<?php
// backend/api/debug_users_tables.php
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

$email = 'brunarafameli@gmail.com';

try {
    $pdo = getDatabaseConnection();
    echo "=== COMPARANDO TABELAS PARA: $email ===\n\n";

    // 1. Verificar na tabela antiga 'users'
    echo "Tabela 'users' (antiga):\n";
    try {
        $stmt1 = $pdo->prepare("SELECT id, email FROM users WHERE email = :email");
        $stmt1->execute([':email' => $email]);
        $u1 = $stmt1->fetch(PDO::FETCH_ASSOC);
        if ($u1) {
            echo "  [OK] Encontrado! ID: {$u1['id']}\n";
        } else {
            echo "  [ERRO] Usuário não encontrado em 'users'.\n";
        }
    } catch (Exception $e) {
        echo "  [ERRO] Tabela 'users' não existe ou erro: " . $e->getMessage() . "\n";
    }

    echo "\n";

    // 2. Verificar na tabela nova 'saas_users'
    echo "Tabela 'saas_users' (nova):\n";
    try {
        $stmt2 = $pdo->prepare("SELECT id, email FROM saas_users WHERE email = :email");
        $stmt2->execute([':email' => $email]);
        $u2 = $stmt2->fetch(PDO::FETCH_ASSOC);
        if ($u2) {
            echo "  [OK] Encontrado! ID: {$u2['id']}\n";
        } else {
            echo "  [ERRO] Usuário não encontrado em 'saas_users'.\n";
        }
    } catch (Exception $e) {
        echo "  [ERRO] Erro ao consultar 'saas_users': " . $e->getMessage() . "\n";
    }

    echo "\n=== LISTA DE TODOS OS USUÁRIOS EM SAAS_USERS ===\n";
    $stmtAll = $pdo->query("SELECT email FROM saas_users ORDER BY email");
    $all = $stmtAll->fetchAll(PDO::FETCH_COLUMN);
    foreach ($all as $e) {
        echo "- $e\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
