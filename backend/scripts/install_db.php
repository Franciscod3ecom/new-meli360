<?php
// backend/scripts/install_db.php

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDatabaseConnection();
    echo "Connected to database successfully.\n";

    $sqlFile = __DIR__ . '/../../schema.sql';
    if (!file_exists($sqlFile)) {
        die("Error: schema.sql not found at $sqlFile\n");
    }

    $sql = file_get_contents($sqlFile);

    // Execute the SQL commands
    $pdo->exec($sql);

    echo "Database schema initialized successfully!\n";
    echo "Created tables: users, accounts, user_accounts, items.\n";

} catch (Exception $e) {
    die("Installation failed: " . $e->getMessage() . "\n");
}
