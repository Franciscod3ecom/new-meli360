<?php
// backend/api/migrate_v3.php
// MIGRATION V3 (PRODUCTION SAFE MODE)
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');

echo "=== MIGRATION V3 (FIX) ===\n\n";

try {
    if (!file_exists(__DIR__ . '/../config/database.php')) {
        throw new Exception("Config file not found!");
    }
    require_once __DIR__ . '/../config/database.php';

    $pdo = getDatabaseConnection();
    echo "[OK] Banco Conectado.\n";

    // Commands
    $columnsToAdd = [
        "ADD COLUMN IF NOT EXISTS total_visits INT DEFAULT 0",
        "ADD COLUMN IF NOT EXISTS last_sale_date TIMESTAMP NULL",
        "ADD COLUMN IF NOT EXISTS date_created TIMESTAMP NULL",
        "ADD COLUMN IF NOT EXISTS available_quantity INT DEFAULT 0"
    ];

    foreach ($columnsToAdd as $index => $colSql) {
        $sql = "ALTER TABLE items " . $colSql;
        try {
            $pdo->exec($sql);
            echo "[OK] Executado: $colSql\n";
        } catch (PDOException $e) {
            echo "[INFO] " . $e->getMessage() . "\n";
        }
    }

    echo "\n=== MIGRATION COMPLETE ===\n";

} catch (Throwable $t) {
    echo "\n[FATAL ERROR] " . $t->getMessage() . "\n";
    http_response_code(500);
}
