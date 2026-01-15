<?php
// backend/api/migrate_v2.php
// Adiciona colunas para Imagens HD e Métricas
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDatabaseConnection();
    echo "Conectado ao banco de dados.\n";

    // 1. secure_thumbnail
    echo "Adicionando secure_thumbnail... ";
    try {
        $pdo->exec("ALTER TABLE items ADD COLUMN secure_thumbnail TEXT");
        echo "OK\n";
    } catch (Exception $e) {
        echo "Já existe ou erro: " . $e->getMessage() . "\n";
    }

    // 2. health
    echo "Adicionando health... ";
    try {
        $pdo->exec("ALTER TABLE items ADD COLUMN health NUMERIC(3, 2) DEFAULT 0.00"); // 0.00 a 1.00
        echo "OK\n";
    } catch (Exception $e) {
        echo "Já existe ou erro: " . $e->getMessage() . "\n";
    }

    // 3. original_price
    echo "Adicionando original_price... ";
    try {
        $pdo->exec("ALTER TABLE items ADD COLUMN original_price NUMERIC(10, 2)");
        echo "OK\n";
    } catch (Exception $e) {
        echo "Já existe ou erro: " . $e->getMessage() . "\n";
    }

    // 4. currency_id
    echo "Adicionando currency_id... ";
    try {
        $pdo->exec("ALTER TABLE items ADD COLUMN currency_id VARCHAR(10) DEFAULT 'BRL'");
        echo "OK\n";
    } catch (Exception $e) {
        echo "Já existe ou erro: " . $e->getMessage() . "\n";
    }
    
    // Atualizar View
    echo "Atualizando items_view... ";
    $pdo->exec("CREATE OR REPLACE VIEW items_view AS
        SELECT 
            *,
            EXTRACT(DAY FROM (NOW() - COALESCE(last_sale_date, date_created))) AS days_without_sale
        FROM items;");
    echo "OK\n";

    echo "\nMigração V2 concluída com sucesso!";

} catch (Exception $e) {
    die("Erro fatal: " . $e->getMessage());
}
