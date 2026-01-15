<?php
// backend/api/fix_db.php
// FORÇA A ATUALIZAÇÃO DO BANCO DE DADOS
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../config/database.php';

echo "=== INICIANDO CORREÇÃO DO BANCO DE DADOS ===\n\n";

try {
    $pdo = getDatabaseConnection();
    echo "1. Conexão OK.\n";

    // Função auxiliar para adicionar coluna
    function addCol($pdo, $table, $col, $type) {
        echo ">> Tentando adicionar '$col' em '$table'...\n";
        try {
            $pdo->exec("ALTER TABLE $table ADD COLUMN $col $type");
            echo "   [SUCESSO] Coluna criada.\n";
        } catch (Exception $e) {
            // Verifica se o erro é "já existe"
            if (strpos($e->getMessage(), 'Duplicate column') !== false || strpos($e->getMessage(), 'already exists') !== false) {
                echo "   [INFO] Coluna já existe.\n";
            } else {
                echo "   [ERRO] " . $e->getMessage() . "\n";
            }
        }
    }

    addCol($pdo, 'items', 'secure_thumbnail', 'TEXT');
    addCol($pdo, 'items', 'health', 'NUMERIC(3, 2) DEFAULT 0.00');
    addCol($pdo, 'items', 'original_price', 'NUMERIC(10, 2)');
    addCol($pdo, 'items', 'currency_id', "VARCHAR(10) DEFAULT 'BRL'");

    echo "\n2. Atualizando Views...\n";
    $pdo->exec("DROP VIEW IF EXISTS items_view");
    $pdo->exec("CREATE VIEW items_view AS
        SELECT 
            *,
            EXTRACT(DAY FROM (NOW() - COALESCE(last_sale_date, date_created))) AS days_without_sale
        FROM items;");
    echo "   [SUCESSO] View atualizada.\n";

    echo "\n=== CONCLUÍDO! AGORA O SYNC DEVE FUNCIONAR ===\n";

} catch (Exception $e) {
    die("ERRO FATAL: " . $e->getMessage());
}
