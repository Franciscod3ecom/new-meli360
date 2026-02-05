<?php
// backend/scripts/fix_schema.php

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDatabaseConnection();
    echo "Connected to database.\n";

    // 1. Drop the incorrect table
    $pdo->exec("DROP TABLE IF EXISTS user_accounts");
    echo "Dropped table 'user_accounts'.\n";

    // 2. Recreate with correct UUID types
    $sql = "
    CREATE TABLE user_accounts (
        user_id UUID REFERENCES users(id) ON DELETE CASCADE,
        account_id UUID REFERENCES accounts(id) ON DELETE CASCADE,
        PRIMARY KEY (user_id, account_id)
    );";

    $pdo->exec($sql);
    echo "Recreated table 'user_accounts' with UUID columns.\n";

} catch (Exception $e) {
    die("Fix failed: " . $e->getMessage() . "\n");
}
