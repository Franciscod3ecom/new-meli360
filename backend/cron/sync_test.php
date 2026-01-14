<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "TEST: Script loaded successfully!\n";
echo "Session test...\n";

session_start();

if (!isset($_SESSION['user_id'])) {
    echo "No session found (expected if accessing directly)\n";
} else {
    echo "Session found: " . $_SESSION['user_id'] . "\n";
}

echo "Database test...\n";
require_once __DIR__ . '/../config/database.php';
$pdo = getDatabaseConnection();
echo "Database connected!\n";

echo "\nAll basic tests passed. The sync.php should work.";
