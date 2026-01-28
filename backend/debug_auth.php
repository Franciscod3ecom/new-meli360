<?php
/**
 * Arquivo: debug_auth.php
 * Descrição: Diagnóstico da tabela saas_users
 */

require_once __DIR__ . '/config/database.php';

header('Content-Type: text/html; charset=utf-8');

try {
    $pdo = getDatabaseConnection();

    echo "<h2>Diagnóstico de Autenticação</h2>";

    // 1. Verificar Estrutura da Tabela
    echo "<h3>1. Estrutura da Tabela 'saas_users'</h3>";
    try {
        $stmt = $pdo->query("SELECT * FROM saas_users LIMIT 0");
        $colCount = $stmt->columnCount();
        echo "<ul>";
        for ($i = 0; $i < $colCount; $i++) {
            $meta = $stmt->getColumnMeta($i);
            echo "<li>" . $meta['name'] . " (" . $meta['native_type'] . ")</li>";
        }
        echo "</ul>";
    } catch (Exception $e) {
        echo "<p style='color:red'>Erro ao ler estrutura: " . $e->getMessage() . "</p>";
    }

    // 2. Listar Usuários
    echo "<h3>2. Usuários Cadastrados</h3>";
    $stmt = $pdo->query("SELECT id, email, password_hash, created_at FROM saas_users ORDER BY id DESC LIMIT 5");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($users) > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Email</th><th>Hash (Início)</th><th>Criado em</th></tr>";
        foreach ($users as $u) {
            echo "<tr>";
            echo "<td>" . $u['id'] . "</td>";
            echo "<td>" . htmlspecialchars($u['email']) . "</td>";
            echo "<td>" . substr($u['password_hash'], 0, 10) . "...</td>";
            echo "<td>" . $u['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p><strong>NENHUM USUÁRIO ENCONTRADO NA TABELA.</strong></p>";
        echo "<p>Isso explica o erro 401. O cadastro não está persistindo.</p>";
    }

    // 3. Teste de Login Manual
    echo "<h3>3. Testar Login (Direct PHP)</h3>";
    echo "<form method='POST' style='background:#f0f0f0; padding:15px; border-radius:5px; max-width:300px;'>";
    echo "Email: <input type='text' name='test_email' required style='width:100%; margin-bottom:10px;'><br>";
    echo "Senha: <input type='text' name='test_pass' required style='width:100%; margin-bottom:10px;'><br>";
    echo "<button type='submit'>Validar Senha</button>";
    echo "</form>";

    if (isset($_POST['test_email'])) {
        $t_email = $_POST['test_email'];
        $t_pass = $_POST['test_pass'];

        echo "<div style='margin-top:10px; padding:10px; border:1px solid #ccc;'>";
        echo "<strong>Resultado do Teste:</strong><br>";

        $stmt = $pdo->prepare("SELECT id, email, password_hash FROM saas_users WHERE email = :email");
        $stmt->execute([':email' => $t_email]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$u) {
            echo "❌ Usuário não encontrado (Email exato).<br>";
            echo "Tentando email minúsculo... ";
            $stmt = $pdo->prepare("SELECT id, email, password_hash FROM saas_users WHERE LOWER(email) = LOWER(:email)");
            $stmt->execute([':email' => $t_email]);
            $u = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($u)
                echo "✅ Encontrado (Case Insensitive)! ID: " . $u['id'] . "<br>";
            else
                echo "❌ Não encontrado nem minúsculo.<br>";
        } else {
            echo "✅ Usuário encontrado.<br>";
        }

        if ($u) {
            if (password_verify($t_pass, $u['password_hash'])) {
                echo "✅ <strong>SENHA CORRETA!</strong><br>";
                echo "O hash bateu perfeitamente.";
            } else {
                echo "❌ <strong>SENHA INCORRETA!</strong><br>";
                echo "Hash no banco: " . substr($u['password_hash'], 0, 10) . "...<br>";
                echo "Senha testada: " . htmlspecialchars($t_pass) . "<br>";
            }
        }
        echo "</div>";
    }

} catch (Exception $e) {
    echo "<h1>Erro Geral</h1>";
    echo "<p>" . $e->getMessage() . "</p>";
}
