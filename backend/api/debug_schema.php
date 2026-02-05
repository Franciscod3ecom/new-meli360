<?php
// backend/api/debug_schema.php
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDatabaseConnection();
    echo "=== DATABASE SCHEMA DEBUG ===\n\n";

    $tables = ['saas_users', 'accounts', 'user_accounts', 'items'];

    foreach ($tables as $table) {
        echo "Table: $table\n";
        $stmt = $pdo->prepare("
            SELECT column_name, data_type, is_nullable
            FROM information_schema.columns
            WHERE table_name = :table
            ORDER BY ordinal_position
        ");
        $stmt->execute([':table' => $table]);
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($columns)) {
            echo "  [NOT FOUND]\n";
        } else {
            foreach ($columns as $col) {
                echo "  - {$col['column_name']}: {$col['data_type']} (Nullable: {$col['is_nullable']})\n";
            }
        }
        echo "\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
