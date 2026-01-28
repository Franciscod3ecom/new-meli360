<?php
/**
 * Script de migração: Criar tabela user_accounts
 * 
 * INSTRUÇÕES:
 * 1. Suba este arquivo para: /public_html/meli360/backend/migrate_user_accounts.php
 * 2. Acesse: https://d3ecom.com.br/meli360/backend/migrate_user_accounts.php?auth=migrate2026
 * 3. DELETE este arquivo depois de usar!
 */

require_once __DIR__ . '/config/database.php';

// Senha temporária para segurança
$TEMP_PASSWORD = 'migrate2026';

if (($_GET['auth'] ?? '') !== $TEMP_PASSWORD) {
    die("❌ Acesso negado. Use: ?auth=$TEMP_PASSWORD");
}

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Migration: user_accounts</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f5f5f5;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
        }

        .success {
            color: green;
            background: #d4edda;
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
        }

        .error {
            color: red;
            background: #f8d7da;
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
        }

        .warning {
            background: #fff3cd;
            padding: 15px;
            border-left: 4px solid #ffc107;
            margin: 10px 0;
        }

        pre {
            background: #f4f4f4;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>🗄️ Migration: Criar Tabela user_accounts</h1>

        <div class="warning">
            <strong>⚠️ ATENÇÃO:</strong> Este script criará a tabela <code>user_accounts</code> no banco de dados
            Supabase.
        </div>

        <?php
        try {
            $pdo = getDatabaseConnection();

            echo "<h2>✅ Conexão com banco: OK</h2>";

            // SQL para criar a tabela
            $sql = "
                CREATE TABLE IF NOT EXISTS user_accounts (
                    user_id INT NOT NULL,
                    account_id UUID NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (user_id, account_id),
                    FOREIGN KEY (user_id) REFERENCES saas_users(id) ON DELETE CASCADE,
                    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
                );
            ";

            echo "<h3>Executando SQL:</h3>";
            echo "<pre>" . htmlspecialchars($sql) . "</pre>";

            $pdo->exec($sql);
            echo "<div class='success'>✅ Tabela <strong>user_accounts</strong> criada com sucesso!</div>";

            // Criar índices
            echo "<h3>Criando índices...</h3>";

            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_accounts_user ON user_accounts(user_id)");
            echo "<div class='success'>✅ Índice <strong>idx_user_accounts_user</strong> criado!</div>";

            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_accounts_account ON user_accounts(account_id)");
            echo "<div class='success'>✅ Índice <strong>idx_user_accounts_account</strong> criado!</div>";

            // Verificar se funcionou
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM user_accounts");
            $result = $stmt->fetch();

            echo "<h2>🎉 Migração Concluída!</h2>";
            echo "<p>A tabela está vazia agora (0 registros). Use <code>link_accounts.php</code> para adicionar vínculos.</p>";

            echo "<div class='warning'>";
            echo "<strong>🚨 PRÓXIMO PASSO:</strong><br>";
            echo "1. DELETE este arquivo (<code>migrate_user_accounts.php</code>)<br>";
            echo "2. Suba o arquivo <code>link_accounts.php</code><br>";
            echo "3. Acesse <a href='link_accounts.php?auth=link2026'>link_accounts.php</a> para vincular contas";
            echo "</div>";

        } catch (Exception $e) {
            echo "<div class='error'>";
            echo "<h2>❌ Erro na Migration:</h2>";
            echo "<p><strong>Mensagem:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";

            // Se o erro for "relation already exists", está OK
            if (strpos($e->getMessage(), 'already exists') !== false) {
                echo "<div class='warning'>ℹ️ A tabela já existe! Tudo certo, pode prosseguir.</div>";
            }

            echo "</div>";
        }
        ?>
    </div>
</body>

</html>