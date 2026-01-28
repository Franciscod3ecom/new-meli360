<?php
/**
 * Arquivo: install_auth_db.php
 * Descrição: Cria a tabela saas_users se não existir.
 */

require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDatabaseConnection();

    $sql = "
    CREATE TABLE IF NOT EXISTS saas_users (
        id SERIAL PRIMARY KEY,
        email VARCHAR(255) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        is_super_admin BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
        updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
    );
    ";

    $pdo->exec($sql);

    echo "<h1>Sucesso!</h1>";
    echo "<p>A tabela <strong>saas_users</strong> foi criada (ou ja existia).</p>";
    echo "<p>Agora o login nativo deve funcionar.</p>";

} catch (Exception $e) {
    echo "<h1>Erro</h1>";
    echo "<p>" . $e->getMessage() . "</p>";
}
