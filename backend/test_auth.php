<?php
// TESTE SIMPLES - Se aparecer esta mensagem, o arquivo está correto!

echo "<h1>✅ Arquivo está correto!</h1>";
echo "<p>Parâmetro recebido: " . ($_GET['auth'] ?? 'NENHUM') . "</p>";
echo "<p>Esperado: link2026</p>";

if (($_GET['auth'] ?? '') === 'link2026') {
    echo "<h2 style='color: green;'>✅ SENHA CORRETA! O problema é cache do navegador.</h2>";
    echo "<h3>Solução:</h3>";
    echo "<ol>";
    echo "<li>Pressione Ctrl+Shift+Delete</li>";
    echo "<li>Marque 'Cookies' e 'Cache'</li>";
    echo "<li>Clique em 'Limpar dados'</li>";
    echo "<li>Recarregue esta página</li>";
    echo "</ol>";
    echo "<hr>";
    echo "<p><a href='link_accounts.php?auth=link2026'>Ir para link_accounts</a></p>";
} else {
    echo "<h2 style='color: red;'>❌ SENHA INCORRETA</h2>";
    echo "<p>Você acessou: " . htmlspecialchars($_SERVER['REQUEST_URI']) . "</p>";
}
?>