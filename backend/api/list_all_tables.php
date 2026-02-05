<?php
// backend/api/list_all_tables.php
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDatabaseConnection();
    echo "=== TODAS AS TABELAS EM TODOS OS SCHEMAS ===\n\n";

    $stmt = $pdo->query("
        SELECT table_schema, table_name 
        FROM information_schema.tables 
        WHERE table_schema NOT IN ('information_schema', 'pg_catalog')
        ORDER BY table_schema, table_name
    ");
    $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($tables as $t) {
        echo "- {$t['table_schema']}.{$t['table_name']}\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
