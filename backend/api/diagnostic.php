<?php
/**
 * DIAGNOSTIC SCRIPT - Verifica Schema e Dados
 * Use para debugar por que as métricas não aparecem no frontend
 */

header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/database.php';

echo "=== MELI360 DIAGNOSTIC TOOL ===\n\n";

try {
    $pdo = getDatabaseConnection();
    echo "[OK] Banco Conectado.\n\n";

    // 1. Verificar Colunas da Tabela ITEMS
    echo "--- SCHEMA DA TABELA 'items' ---\n";
    $stmt = $pdo->query("
        SELECT column_name, data_type, is_nullable
        FROM information_schema.columns
        WHERE table_name = 'items'
        ORDER BY ordinal_position
    ");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($columns)) {
        echo "[ERRO] Tabela 'items' não existe!\n";
    } else {
        foreach ($columns as $col) {
            echo "  - {$col['column_name']} ({$col['data_type']}) " . 
                 ($col['is_nullable'] === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
        }
    }

    // 2. Verificar se as colunas críticas existem
    echo "\n--- VERIFICAÇÃO DE COLUNAS CRÍTICAS ---\n";
    $criticalColumns = ['health', 'catalog_listing', 'last_sale_date', 'total_visits', 'date_created'];
    $existingColumns = array_column($columns, 'column_name');
    
    foreach ($criticalColumns as $col) {
        $exists = in_array($col, $existingColumns);
        echo "  " . ($exists ? "✓" : "✗") . " $col\n";
    }

    // 3. Contar Total de Itens
    echo "\n--- ESTATÍSTICAS DE DADOS ---\n";
    $total = $pdo->query("SELECT COUNT(*) FROM items")->fetchColumn();
    echo "  Total de itens: $total\n";

    if ($total > 0) {
        // 4. Amostra de Dados (1º item)
        echo "\n--- AMOSTRA DO PRIMEIRO ITEM ---\n";
        $sample = $pdo->query("SELECT * FROM items LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        
        if ($sample) {
            echo "  ml_id: {$sample['ml_id']}\n";
            echo "  title: " . substr($sample['title'] ?? 'NULL', 0, 50) . "\n";
            echo "  health: " . ($sample['health'] ?? 'NULL') . "\n";
            echo "  catalog_listing: " . ($sample['catalog_listing'] ?? 'NULL') . "\n";
            echo "  last_sale_date: " . ($sample['last_sale_date'] ?? 'NULL') . "\n";
            echo "  total_visits: " . ($sample['total_visits'] ?? 'NULL') . "\n";
            echo "  date_created: " . ($sample['date_created'] ?? 'NULL') . "\n";
        }

        // 5. Contar quantos items têm dados preenchidos
        echo "\n--- PREENCHIMENTO DE DADOS ---\n";
        
        if (in_array('health', $existingColumns)) {
            $withHealth = $pdo->query("SELECT COUNT(*) FROM items WHERE health IS NOT NULL")->fetchColumn();
            echo "  Items com 'health': $withHealth de $total\n";
        }
        
        if (in_array('catalog_listing', $existingColumns)) {
            $withCatalog = $pdo->query("SELECT COUNT(*) FROM items WHERE catalog_listing = TRUE")->fetchColumn();
            echo "  Items com 'catalog_listing = TRUE': $withCatalog de $total\n";
        }
        
        if (in_array('last_sale_date', $existingColumns)) {
            $withSales = $pdo->query("SELECT COUNT(*) FROM items WHERE last_sale_date IS NOT NULL")->fetchColumn();
            echo "  Items com 'last_sale_date': $withSales de $total\n";
        }
    }

    echo "\n=== DIAGNÓSTICO COMPLETO ===\n";

} catch (Throwable $e) {
    echo "\n[ERRO FATAL] " . $e->getMessage() . "\n";
    echo "Stack: " . $e->getTraceAsString() . "\n";
}
?>
