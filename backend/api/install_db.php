<?php
/**
 * Arquivo: install_db.php
 * Descrição: Script ÚNICO de inicialização e atualização do Banco de Dados.
 * Cria todas as tabelas necessárias e aplica as colunas mais recentes.
 */

header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/database.php';

echo "=== MELI360 DATABASE INSTALLER ===\n\n";

try {
    $pdo = getDatabaseConnection();
    echo "[OK] Banco Conectado.\n";

    // 1. Tabela ACCOUNTS (Multi-Contas)
    $sqlAccounts = "CREATE TABLE IF NOT EXISTS accounts (
        id SERIAL PRIMARY KEY,
        ml_user_id BIGINT UNIQUE NOT NULL,
        nickname VARCHAR(255),
        access_token TEXT NOT NULL,
        refresh_token TEXT NOT NULL,
        expires_at TIMESTAMP,
        updated_at TIMESTAMP DEFAULT NOW()
    )";
    $pdo->exec($sqlAccounts);
    echo "[OK] Tabela 'accounts' verificada.\n";

    // 2. Tabela ITEMS (Anúncios)
    $sqlItems = "CREATE TABLE IF NOT EXISTS items (
        id SERIAL PRIMARY KEY,
        account_id INT REFERENCES accounts(id) ON DELETE CASCADE,
        ml_id VARCHAR(50) UNIQUE NOT NULL,
        title TEXT,
        price DECIMAL(10,2),
        currency_id VARCHAR(3) DEFAULT 'BRL',
        original_price DECIMAL(10,2),
        status VARCHAR(50),
        permalink TEXT,
        thumbnail TEXT,
        secure_thumbnail TEXT,
        sold_quantity INT DEFAULT 0,
        available_quantity INT DEFAULT 0,
        shipping_mode VARCHAR(50),
        logistic_type VARCHAR(50),
        free_shipping BOOLEAN DEFAULT FALSE,
        date_created TIMESTAMP,
        last_sale_date TIMESTAMP,
        total_visits INT DEFAULT 0,
        health DECIMAL(3,2) DEFAULT NULL,
        catalog_listing BOOLEAN DEFAULT FALSE,
        category_name TEXT,
        shipping_cost_nacional DECIMAL(10,2),
        billable_weight DECIMAL(10,3),
        weight_status VARCHAR(50),
        freight_brasilia DECIMAL(10,2),
        freight_sao_paulo DECIMAL(10,2),
        freight_salvador DECIMAL(10,2),
        freight_manaus DECIMAL(10,2),
        freight_porto_alegre DECIMAL(10,2),
        me2_restrictions TEXT,
        updated_at TIMESTAMP DEFAULT NOW()
    )";
    $pdo->exec($sqlItems);
    echo "[OK] Tabela 'items' verificada.\n";

    // 3. Updates de Schema (Garantir colunas novas em tabelas antigas)
    $updates = [
        "ALTER TABLE items ADD COLUMN IF NOT EXISTS secure_thumbnail TEXT",
        "ALTER TABLE items ADD COLUMN IF NOT EXISTS original_price DECIMAL(10,2)",
        "ALTER TABLE items ADD COLUMN IF NOT EXISTS currency_id VARCHAR(3) DEFAULT 'BRL'",
        "ALTER TABLE items ADD COLUMN IF NOT EXISTS health DECIMAL(3,2) DEFAULT NULL",
        "ALTER TABLE items ADD COLUMN IF NOT EXISTS catalog_listing BOOLEAN DEFAULT FALSE",
        "ALTER TABLE items ADD COLUMN IF NOT EXISTS account_id INT REFERENCES accounts(id) ON DELETE CASCADE",
        "ALTER TABLE items ADD COLUMN IF NOT EXISTS category_name TEXT",
        "ALTER TABLE items ADD COLUMN IF NOT EXISTS shipping_cost_nacional DECIMAL(10,2)",
        "ALTER TABLE items ADD COLUMN IF NOT EXISTS billable_weight DECIMAL(10,3)",
        "ALTER TABLE items ADD COLUMN IF NOT EXISTS weight_status VARCHAR(50)",
        "ALTER TABLE items ADD COLUMN IF NOT EXISTS freight_brasilia DECIMAL(10,2)",
        "ALTER TABLE items ADD COLUMN IF NOT EXISTS freight_sao_paulo DECIMAL(10,2)",
        "ALTER TABLE items ADD COLUMN IF NOT EXISTS freight_salvador DECIMAL(10,2)",
        "ALTER TABLE items ADD COLUMN IF NOT EXISTS freight_manaus DECIMAL(10,2)",
        "ALTER TABLE items ADD COLUMN IF NOT EXISTS freight_porto_alegre DECIMAL(10,2)",
        "ALTER TABLE items ADD COLUMN IF NOT EXISTS me2_restrictions TEXT"
    ];

    foreach ($updates as $sql) {
        try {
            $pdo->exec($sql);
            echo "[UPDATE] Executado: " . substr($sql, 0, 50) . "...\n";
        } catch (Exception $e) {
            // Ignora erro se coluna já existe (fallback)
            echo "[SKIP] " . $e->getMessage() . "\n";
        }
    }

    echo "\n=== INSTALAÇÃO CONCLUÍDA COM SUCESSO ===\n";
    echo "Agora você pode configurar o Cron Job para: backend/cron/sync.php\n";

} catch (Throwable $e) {
    http_response_code(500);
    echo "\n[ERRO FATAL] " . $e->getMessage() . "\n";
}
?>