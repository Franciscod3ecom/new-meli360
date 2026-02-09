<?php
/**
 * Migração: Adiciona colunas reset_token e reset_expires_at à tabela saas_users.
 * Acesse via navegador para executar. REMOVA este arquivo após a execução.
 */

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDatabaseConnection();

    $pdo->exec("
        ALTER TABLE saas_users
            ADD COLUMN IF NOT EXISTS reset_token VARCHAR(255) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS reset_expires_at TIMESTAMP WITH TIME ZONE DEFAULT NULL
    ");

    echo "<h1>Migração executada com sucesso!</h1>";
    echo "<p>Colunas <strong>reset_token</strong> e <strong>reset_expires_at</strong> adicionadas à tabela <strong>saas_users</strong>.</p>";
    echo "<p style='color:red;'><strong>IMPORTANTE:</strong> Delete este arquivo do servidor agora.</p>";

} catch (Exception $e) {
    http_response_code(500);
    echo "<h1>Erro na migração</h1>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
