<?php
// Script para corrigir colunas faltantes no Banco de Dados
// Execute ONE-TIME
header('Content-Type: text/html; charset=utf-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);

// CORREÇÃO: O arquivo está em /backend/, então config está em ./config/
require_once __DIR__ . '/config/database.php';

echo "<h1>🛠️ Database Repair Tool</h1>";

try {
    $pdo = getDatabaseConnection();

    // Lista de colunas para verificar/adicionar
    $commands = [
        "ALTER TABLE items ADD COLUMN IF NOT EXISTS date_created TIMESTAMP",
        "ALTER TABLE items ADD COLUMN IF NOT EXISTS total_visits INT DEFAULT 0",
        "ALTER TABLE items ADD COLUMN IF NOT EXISTS last_sale_date TIMESTAMP",

        "ALTER TABLE items ADD COLUMN IF NOT EXISTS shipping_mode VARCHAR(50)",
        "ALTER TABLE items ADD COLUMN IF NOT EXISTS logistic_type VARCHAR(50)",
        "ALTER TABLE items ADD COLUMN IF NOT EXISTS free_shipping BOOLEAN DEFAULT FALSE",

        "ALTER TABLE items ADD COLUMN IF NOT EXISTS secure_thumbnail TEXT",
        "ALTER TABLE items ADD COLUMN IF NOT EXISTS health DECIMAL(3,2)",
        "ALTER TABLE items ADD COLUMN IF NOT EXISTS catalog_listing VARCHAR(10)",
        "ALTER TABLE items ADD COLUMN IF NOT EXISTS original_price DECIMAL(10,2)",
        "ALTER TABLE items ADD COLUMN IF NOT EXISTS currency_id VARCHAR(10)",

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

    foreach ($commands as $sql) {
        try {
            $pdo->exec($sql);
            echo "<p style='color: green;'>✅ Executado: $sql</p>";
        } catch (Exception $e) {
            // Em alguns bancos (versões antigas de PG), IF NOT EXISTS pode falhar, então ignoramos se for erro de coluna existente
            echo "<p style='color: orange;'>⚠️ Aviso: " . $e->getMessage() . "</p>";
        }
    }

    echo "<h2>🎉 Correção concluída!</h2>";
    echo "<p>Agora execute o Sync novamente no Dashboard para preencher os dados.</p>";

} catch (Exception $e) {
    echo "<h1>❌ FATAL ERROR</h1>";
    echo $e->getMessage();
}