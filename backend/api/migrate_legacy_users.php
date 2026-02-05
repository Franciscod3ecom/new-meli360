<?php
/**
 * Arquivo: migrate_legacy_users.php
 * Descrição: Migra usuários da tabela antiga 'users' (UUID) para a nova 'saas_users' (Serial/Int).
 */

header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

echo "=== MIGRAÇÃO DE USUÁRIOS: users -> saas_users ===\n\n";

try {
    $pdo = getDatabaseConnection();

    // 1. Buscar todos os usuários da tabela antiga
    echo "1. Lendo usuários da tabela 'users'...\n";
    $stmtSource = $pdo->query("SELECT email, password, created_at, updated_at FROM users");
    $legacyUsers = $stmtSource->fetchAll(PDO::FETCH_ASSOC);

    echo "   Encontrados " . count($legacyUsers) . " usuários para migrar.\n\n";

    $migratedCount = 0;
    $skippedCount = 0;

    // 2. Inserir na nova tabela
    $stmtCheck = $pdo->prepare("SELECT id FROM saas_users WHERE email = :email LIMIT 1");
    $stmtInsert = $pdo->prepare("
        INSERT INTO saas_users (email, password_hash, created_at, updated_at)
        VALUES (:email, :pwd, :created, :updated)
        ON CONFLICT (email) DO NOTHING
    ");

    foreach ($legacyUsers as $user) {
        // Verificar se já existe para evitar duplicidade manual
        $stmtCheck->execute([':email' => $user['email']]);
        if ($stmtCheck->fetch()) {
            echo "[PULADO] {$user['email']} (já existe em saas_users)\n";
            $skippedCount++;
            continue;
        }

        // Inserir
        $stmtInsert->execute([
            ':email' => $user['email'],
            ':pwd' => $user['password'], // Mantém o hash original
            ':created' => $user['created_at'],
            ':updated' => $user['updated_at']
        ]);

        if ($stmtInsert->rowCount() > 0) {
            echo "[OK] Migrado: {$user['email']}\n";
            $migratedCount++;
        } else {
            echo "[ERRO] Falha ao migrar: {$user['email']}\n";
        }
    }

    echo "\n=== RESUMO DA MIGRAÇÃO ===\n";
    echo "Migrados com sucesso: $migratedCount\n";
    echo "Pulados (já existentes): $skippedCount\n";
    echo "Total processado: " . ($migratedCount + $skippedCount) . "\n";
    echo "\n[PRONTO] Todos os usuários agora podem logar na nova interface.\n";

} catch (Throwable $e) {
    echo "\n[ERRO FATAL] " . $e->getMessage() . "\n";
}
