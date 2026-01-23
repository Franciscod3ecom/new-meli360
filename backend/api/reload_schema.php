<?php
/**
 * Arquivo: reload_schema.php
 * Descrição: Força a atualização do cache da API do Supabase (PostgREST).
 * Isso é necessário quando se cria colunas via conexão direta (PDO) e elas não aparecem no Frontend.
 */

header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/database.php';

echo "=== FORÇAR RECARGA DE SCHEMA SUPABASE/POSTGREST ===\n\n";

try {
    $pdo = getDatabaseConnection();
    echo "[OK] Banco Conectado.\n";

    // Comando mágico para recarregar a configuração do PostgREST
    $sql = "NOTIFY pgrst, 'reload config'";

    $pdo->exec($sql);
    echo "[SUCCESS] Comando 'NOTIFY pgrst, 'reload config'' enviado!\n\n";

    echo "A API do Supabase deve ter atualizado o esquema agora.\n";
    echo "Tente recarregar o frontend (Ctrl+Shift+R) e verifique se as colunas apareceram.\n";

} catch (Throwable $e) {
    echo "\n[ERRO] " . $e->getMessage() . "\n";
}
?>