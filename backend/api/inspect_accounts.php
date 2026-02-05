<?php
// backend/api/inspect_accounts.php
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDatabaseConnection();
    echo "=== INSPEÇÃO DA TABELA 'accounts' ===\n\n";

    $stmt = $pdo->prepare("
        SELECT column_name, data_type 
        FROM information_schema.columns 
        WHERE table_name = 'accounts'
        ORDER BY ordinal_position
    ");
    $stmt->execute();
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($cols as $c) {
        echo "- {$c['column_name']}: {$c['data_type']}\n";
    }

    echo "\n=== AMOSTRA DE DADOS (TOP 3) ===\n";
    $stmtData = $pdo->query("SELECT * FROM accounts LIMIT 3");
    $data = $stmtData->fetchAll(PDO::FETCH_ASSOC);
    print_r($data);

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
