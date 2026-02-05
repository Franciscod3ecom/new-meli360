<?php
// backend/api/global_search.php
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

$searchTerm = 'brunarafameli@gmail.com';

try {
    $pdo = getDatabaseConnection();
    echo "=== BUSCA GLOBAL POR: $searchTerm ===\n\n";

    $stmt = $pdo->query("
        SELECT table_name 
        FROM information_schema.tables 
        WHERE table_schema = 'public'
    ");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        $stmtCols = $pdo->prepare("
            SELECT column_name 
            FROM information_schema.columns 
            WHERE table_name = :table AND data_type IN ('character varying', 'text', 'jsonb')
        ");
        $stmtCols->execute([':table' => $table]);
        $cols = $stmtCols->fetchAll(PDO::FETCH_COLUMN);

        foreach ($cols as $col) {
            $sql = "SELECT COUNT(*) FROM \"$table\" WHERE CAST(\"$col\" AS TEXT) ILIKE :search";
            $stmtSearch = $pdo->prepare($sql);
            $stmtSearch->execute([':search' => "%$searchTerm%"]);
            $count = $stmtSearch->fetchColumn();

            if ($count > 0) {
                echo "[ACHEI] Tabela: $table | Coluna: $col | Ocorrências: $count\n";

                // Mostrar amostra
                $sqlSample = "SELECT * FROM \"$table\" WHERE CAST(\"$col\" AS TEXT) ILIKE :search LIMIT 1";
                $stmtSample = $pdo->prepare($sqlSample);
                $stmtSample->execute([':search' => "%$searchTerm%"]);
                $sample = $stmtSample->fetch(PDO::FETCH_ASSOC);
                print_r($sample);
                echo "-------------------\n";
            }
        }
    }

    if ($migratedCount === 0) {
        echo "Nenhuma ocorrência encontrada fora das tabelas de usuários.\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
