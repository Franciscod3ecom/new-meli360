<?php
/**
 * Arquivo: update_auth_schema.php
 * Descrição: Script para garantir que a tabela saas_users tenha as colunas de recuperação de senha.
 */

header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

echo "=== ATUALIZAÇÃO DE SCHEMA: saas_users ===\n\n";

try {
    $pdo = getDatabaseConnection();

    $queries = [
        "ALTER TABLE saas_users ADD COLUMN IF NOT EXISTS reset_token VARCHAR(64) DEFAULT NULL",
        "ALTER TABLE saas_users ADD COLUMN IF NOT EXISTS reset_expires_at TIMESTAMP DEFAULT NULL"
    ];

    foreach ($queries as $sql) {
        echo "Executando: $sql\n";
        $pdo->exec($sql);
    }

    echo "\n[SUCESSO] Colunas de recuperação adicionadas à tabela 'saas_users'.\n";

} catch (Throwable $e) {
    echo "\n[ERRO] " . $e->getMessage() . "\n";
}
