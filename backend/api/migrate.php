<?php
// backend/api/migrate.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDatabaseConnection();
    
    $queries = [
        // 1. Add Sync Fields to Accounts
        "ALTER TABLE accounts ADD COLUMN IF NOT EXISTS sync_status VARCHAR(50) DEFAULT 'IDLE'",
        "ALTER TABLE accounts ADD COLUMN IF NOT EXISTS sync_scroll_id TEXT",
        "ALTER TABLE accounts ADD COLUMN IF NOT EXISTS sync_last_message TEXT",
        "ALTER TABLE accounts ADD COLUMN IF NOT EXISTS sync_last_run_at TIMESTAMP WITH TIME ZONE",
        
        // 2. Add Analisador Fields to Items
        "ALTER TABLE items ADD COLUMN IF NOT EXISTS total_visits INT DEFAULT 0",
        "ALTER TABLE items ADD COLUMN IF NOT EXISTS available_quantity INT DEFAULT 0",
        "ALTER TABLE items ADD COLUMN IF NOT EXISTS is_synced INT DEFAULT 0", // 0: Pending, 1: Synced, 2: Update Requested
        
        // 3. Update View (We drop and recreate because Views can't be easily altered if structure changes)
        "DROP VIEW IF EXISTS items_view",
        "CREATE VIEW items_view AS
         SELECT 
             *,
             EXTRACT(DAY FROM (NOW() - COALESCE(last_sale_date, date_created))) AS days_without_sale
         FROM items"
    ];

    $results = [];
    foreach ($queries as $sql) {
        try {
            $pdo->exec($sql);
            $results[] = ['status' => 'success', 'query' => substr($sql, 0, 50) . '...'];
        } catch (PDOException $e) {
            // Ignore "column already exists" errors locally if manageable, but better to just report
            $results[] = ['status' => 'warning', 'query' => substr($sql, 0, 50) . '...', 'error' => $e->getMessage()];
        }
    }

    echo json_encode(['success' => true, 'migrations' => $results]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
