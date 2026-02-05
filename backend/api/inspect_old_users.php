<?php
// backend/api/inspect_old_users.php
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDatabaseConnection();
    echo "=== INSPEÇÃO DA TABELA 'users' ===\n\n";

    $stmt = $pdo->prepare("
        SELECT column_name, data_type 
        FROM information_schema.columns 
        WHERE table_name = 'users'
    ");
    $stmt->execute();
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($cols)) {
        echo "Tabela 'users' não encontrada.\n";
    } else {
        foreach ($cols as $c) {
            echo "- {$c['column_name']}: {$c['data_type']}\n";
        }
    }

    echo "\n=== AMOSTRA DE DADOS (TOP 5) ===\n";
    $stmtData = $pdo->query("SELECT * FROM users LIMIT 5");
    $data = $stmtData->fetchAll(PDO::FETCH_ASSOC);
    print_r($data);

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
