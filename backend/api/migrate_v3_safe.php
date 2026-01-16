<?php
// backend/api/migrate_v3_safe.php
// SAFE MODE MIGRATION V3
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');

echo "=== MIGRATION V3 (SAFE MODE) ===\n\n";

try {
    echo "1. Loading Database Config...\n";
    if (!file_exists(__DIR__ . '/../config/database.php')) {
        throw new Exception("Config file not found!");
    }
    require_once __DIR__ . '/../config/database.php';

    echo "2. Connecting to Database...\n";
    $pdo = getDatabaseConnection();
    echo "   [OK] Connected.\n";

    // Commands
    $columnsToAdd = [
        "ADD COLUMN IF NOT EXISTS total_visits INT DEFAULT 0",
        "ADD COLUMN IF NOT EXISTS last_sale_date TIMESTAMP NULL", // Postgres uses TIMESTAMP
        "ADD COLUMN IF NOT EXISTS date_created TIMESTAMP NULL",
        "ADD COLUMN IF NOT EXISTS available_quantity INT DEFAULT 0"
    ];

    foreach ($columnsToAdd as $index => $colSql) {
        echo "3." . ($index + 1) . " Executing: $colSql ... ";
        $sql = "ALTER TABLE items " . $colSql;
        try {
            $pdo->exec($sql);
            echo "[OK]\n";
        } catch (PDOException $e) {
            echo "[IGNORED/ERROR] " . $e->getMessage() . "\n";
        }
    }

    echo "\n=== MIGRATION COMPLETE ===\n";

} catch (Throwable $t) {
    echo "\n[FATAL ERROR] " . $t->getMessage() . "\n";
    echo "File: " . $t->getFile() . " Line: " . $t->getLine() . "\n";
    print_r($t->getTraceAsString());
}
