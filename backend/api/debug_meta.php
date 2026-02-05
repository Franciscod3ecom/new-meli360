<?php
// backend/api/debug_meta.php
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDatabaseConnection();
    echo "=== INSPEÇÃO DE METADATA (users) ===\n\n";

    $stmt = $pdo->query("SELECT email, raw_user_meta_data FROM users WHERE email IS NOT NULL LIMIT 10");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($users as $u) {
        echo "Email: {$u['email']}\n";
        echo "Meta: " . $u['raw_user_meta_data'] . "\n";
        echo "-------------------\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
