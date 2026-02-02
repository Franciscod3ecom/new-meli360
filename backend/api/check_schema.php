<?php
// backend/api/check_schema.php
require_once __DIR__ . '/../config/database.php';
try {
    $pdo = getDatabaseConnection();
    $stmt = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'items'");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
