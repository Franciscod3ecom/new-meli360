<?php
/**
 * Arquivo: fix_user_accounts_schema.php
 * Descrição: Corrige o tipo de dados da tabela user_accounts para evitar erro de UUID vs Integer.
 */

header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

echo "=== CORREÇÃO DE SCHEMA: user_accounts ===\n\n";

try {
    $pdo = getDatabaseConnection();

    echo "1. Removendo tabela user_accounts (se existir)...\n";
    $pdo->exec("DROP TABLE IF EXISTS user_accounts CASCADE");

    echo "2. Recriando tabela user_accounts com tipos corretos...\n";
    // user_id deve ser INT (para saas_users)
    // account_id deve ser UUID (para accounts)
    $sql = "CREATE TABLE user_accounts (
        user_id INT REFERENCES saas_users(id) ON DELETE CASCADE,
        account_id UUID REFERENCES accounts(id) ON DELETE CASCADE,
        PRIMARY KEY (user_id, account_id)
    )";
    $pdo->exec($sql);

    echo "3. Verificando tipos finais...\n";
    $stmt = $pdo->prepare("
        SELECT column_name, data_type 
        FROM information_schema.columns 
        WHERE table_name = 'user_accounts'
    ");
    $stmt->execute();
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        echo "   - {$c['column_name']}: {$c['data_type']}\n";
    }

    echo "\n[SUCESSO] Schema corrigido. Por favor, vincule suas contas novamente se necessário.\n";

} catch (Throwable $e) {
    echo "\n[ERRO] " . $e->getMessage() . "\n";
}
