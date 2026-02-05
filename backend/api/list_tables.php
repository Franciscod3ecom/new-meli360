<?php
// backend/api/list_tables.php
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDatabaseConnection();
    echo "=== LISTA DE TODAS AS TABELAS ===\n\n";

    $stmt = $pdo->query("
        SELECT table_name 
        FROM information_schema.tables 
        WHERE table_schema = 'public'
        ORDER BY table_name
    ");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        echo "- $table\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
