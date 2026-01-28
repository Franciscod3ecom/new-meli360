<?php
/**
 * Script de vinculação manual: Link user with ML accounts
 * 
 * USO ÚNICO - DELETE IMEDIATAMENTE APÓS USAR
 * 
 * Este script não tem senha para evitar problemas de servidor ignorando parâmetros URL.
 */

require_once __DIR__ . '/config/database.php';

// --- REMOVIDA A VERIFICAÇÃO DE SENHA PARA FACILITAR O ACESSO --- //

try {
    $pdo = getDatabaseConnection();

    // Se form foi enviado, fazer o link
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $userId = (int) $_POST['user_id'];
        $accountId = $_POST['account_id'];

        // Verificar se já existe
        $stmt = $pdo->prepare("SELECT * FROM user_accounts WHERE user_id = :user_id AND account_id = :account_id");
        $stmt->execute([':user_id' => $userId, ':account_id' => $accountId]);

        if ($stmt->fetch()) {
            echo "<div style='color: orange; padding: 15px; background: #fff3cd; margin: 10px 0; border: 1px solid #ffeeba; border-radius: 4px;'>⚠️ Este vínculo já existe!</div>";
        } else {
            // Tentar inserir - se falhar por FK, mostra erro
            try {
                $stmt = $pdo->prepare("INSERT INTO user_accounts (user_id, account_id) VALUES (:user_id, :account_id)");
                $stmt->execute([':user_id' => $userId, ':account_id' => $accountId]);
                echo "<div style='color: green; padding: 15px; background: #d4edda; margin: 10px 0; border: 1px solid #c3e6cb; border-radius: 4px;'>✅ SUCESSO! Conta vinculada.</div>";
            } catch (PDOException $ex) {
                echo "<div style='color: red; padding: 15px; background: #f8d7da; margin: 10px 0;'>Erro ao criar vínculo: " . htmlspecialchars($ex->getMessage()) . "</div>";
            }
        }
    }

    // Listar usuários SaaS (login email/senha)
    $users = $pdo->query("SELECT id, email, created_at FROM saas_users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

    // Listar contas ML (login oauth) - REMOVIDO created_at que estava dando erro
    $accounts = $pdo->query("SELECT id, ml_user_id, nickname FROM accounts ORDER BY nickname")->fetchAll(PDO::FETCH_ASSOC);

    // Listar vínculos existentes
    $links = $pdo->query("
        SELECT ua.*, su.email, a.nickname 
        FROM user_accounts ua
        JOIN saas_users su ON ua.user_id = su.id
        JOIN accounts a ON ua.account_id = a.id
        ORDER BY su.email, a.nickname
    ")->fetchAll(PDO::FETCH_ASSOC);

    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">

    <head>
        <meta charset="UTF-8">
        <title>Vincular Contas (Modo Livre)</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                padding: 40px;
                background: #f0f2f5;
            }

            .container {
                max-width: 900px;
                margin: 0 auto;
                background: white;
                padding: 30px;
                border-radius: 12px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            }

            h1 {
                color: #1a1a1a;
                margin-top: 0;
            }

            h2 {
                color: #444;
                margin-top: 30px;
                border-bottom: 2px solid #eee;
                padding-bottom: 10px;
            }

            select {
                padding: 12px;
                margin: 10px 0;
                width: 100%;
                border: 1px solid #ccc;
                border-radius: 6px;
                font-size: 16px;
            }

            button {
                background: #007bff;
                color: white;
                border: none;
                padding: 15px 30px;
                width: 100%;
                font-size: 16px;
                font-weight: bold;
                border-radius: 6px;
                cursor: pointer;
                transition: background 0.2s;
            }

            button:hover {
                background: #0056b3;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 15px;
            }

            th {
                text-align: left;
                background: #f8f9fa;
                padding: 12px;
                border-bottom: 2px solid #dee2e6;
                color: #666;
                font-size: 14px;
                text-transform: uppercase;
            }

            td {
                padding: 12px;
                border-bottom: 1px solid #dee2e6;
            }

            tr:last-child td {
                border-bottom: none;
            }

            .badge {
                display: inline-block;
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 12px;
                font-weight: bold;
            }

            .badge-user {
                background: #e3f2fd;
                color: #0d47a1;
            }

            .badge-ml {
                background: #fff3cd;
                color: #856404;
            }

            .warning-box {
                background: #fff3cd;
                border: 1px solid #ffeeba;
                color: #856404;
                padding: 15px;
                border-radius: 6px;
                margin-bottom: 20px;
            }
        </style>
    </head>

    <body>
        <div class="container">
            <div class="warning-box">
                <strong>🚨 IMPORTANTE:</strong> Como removemos a senha deste arquivo, <strong>APAGUE-O</strong> do servidor
                assim que terminar de vincular suas contas!
            </div>

            <h1>🔗 Vincular Contas</h1>
            <p>Selecione seu login de Email (SaaS Users) e a Loja do Mercado Livre (Accounts) que ele deve ter acesso.</p>

            <form method="POST" style="background: #fafafa; padding: 20px; border-radius: 8px; border: 1px solid #eee;">
                <label><strong>1. Quem é você? (Login Email/Senha)</strong></label>
                <select name="user_id" required>
                    <option value="">Selecione seu email...</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?= $user['id'] ?>">
                            <?= htmlspecialchars($user['email']) ?> (ID: <?= $user['id'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>

                <label><strong>2. Qual conta ML você quer acessar?</strong></label>
                <select name="account_id" required>
                    <option value="">Selecione a loja...</option>
                    <?php foreach ($accounts as $acc): ?>
                        <option value="<?= $acc['id'] ?>">
                            <?= htmlspecialchars($acc['nickname']) ?> (ML ID: <?= $acc['ml_user_id'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit">Vincular Agora 🔗</button>
            </form>

            <h2>✅ Vínculos Ativos</h2>
            <?php if (empty($links)): ?>
                <p>Nenhum vínculo encontrado. O usuário "Email" não vê dados de nenhuma conta ML.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Login (Email)</th>
                            <th>Acessa a Loja (ML)</th>
                            <th>Data Vínculo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($links as $link): ?>
                            <tr>
                                <td><span class="badge badge-user"><?= htmlspecialchars($link['email']) ?></span></td>
                                <td><span class="badge badge-ml"><?= htmlspecialchars($link['nickname']) ?></span></td>
                                <td><?= date('d/m/Y H:i', strtotime($link['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </body>

    </html>
    <?php

} catch (Exception $e) {
    echo "<div style='color: red; padding: 20px; font-family: sans-serif;'>";
    echo "<h1>❌ Erro Fatal</h1>";
    echo "<p>Não foi possível conectar ao banco de dados ou executar a consulta.</p>";
    echo "<pre style='background: #f8d7da; padding: 15px; border-radius: 5px;'>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "</div>";
}
