<?php
// backend/api/debug_db.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDatabaseConnection();
    echo "=== DATABASE DEBUG ===\n\n";

    // 1. Check Columns
    echo "1. Table Structure (items):\n";
    $stmt = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'items'");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "- " . $col['column_name'] . " (" . $col['data_type'] . ")\n";
    }

    // 2. Check Data Sample
    echo "\n2. Data Sample (First 5 items):\n";
    $stmt = $pdo->query("SELECT id, ml_id, title, price, health, secure_thumbnail FROM items LIMIT 5");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($items)) {
        echo "No items found in table.\n";
    } else {
        print_r($items);
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
