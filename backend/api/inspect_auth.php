<?php
// backend/api/inspect_auth.php
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDatabaseConnection();
    echo "=== INSPEÇÃO DO SCHEMA 'auth' ===\n\n";

    $email = 'brunarafameli@gmail.com';

    // 1. Procurar na auth.users
    echo "Procurando em auth.users por: $email\n";
    $stmt = $pdo->prepare("SELECT id, email, raw_user_meta_data FROM auth.users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $authUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($authUser) {
        echo "  [OK] Encontrado em auth.users!\n";
        echo "  ID: {$authUser['id']}\n";
        echo "  Meta: " . $authUser['raw_user_meta_data'] . "\n";
    } else {
        echo "  [AVISO] Não encontrado em auth.users.\n";
    }

    echo "\n";

    // 2. Procurar na auth.identities
    if ($authUser) {
        echo "Procurando em auth.identities pelo user_id: {$authUser['id']}\n";
        $stmtId = $pdo->prepare("SELECT provider, provider_id, identity_data FROM auth.identities WHERE user_id = :uid");
        $stmtId->execute([':uid' => $authUser['id']]);
        $identities = $stmtId->fetchAll(PDO::FETCH_ASSOC);

        if (empty($identities)) {
            echo "  [AVISO] Nenhuma identidade encontrada.\n";
        } else {
            foreach ($identities as $id) {
                echo "  - Provider: {$id['provider']} | ID: {$id['provider_id']}\n";
                echo "    Data: {$id['identity_data']}\n";
            }
        }
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
