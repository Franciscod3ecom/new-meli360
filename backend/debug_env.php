<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Debug de Ambiente</h1>";

$expectedPath = __DIR__ . '/.env';
echo "<p>Caminho esperado do .env: <code>$expectedPath</code></p>";

if (file_exists($expectedPath)) {
    echo "<p style='color:green'>✅ O arquivo .env EXISTE.</p>";
    echo "<p>Permissões: " . substr(sprintf('%o', fileperms($expectedPath)), -4) . "</p>";
    
    $contents = file_get_contents($expectedPath);
    echo "<p>Conteúdo (Primeiros 50 chars): " . htmlspecialchars(substr($contents, 0, 50)) . "...</p>";
} else {
    echo "<p style='color:red'>❌ O arquivo .env NÃO foi encontrado neste caminho.</p>";
    echo "<p>Conteúdo da pasta atual (" . __DIR__ . "):</p>";
    $files = scandir(__DIR__);
    echo "<ul>";
    foreach ($files as $file) {
        echo "<li>$file</li>";
    }
    echo "</ul>";
}

echo "<h2>Variáveis de Ambiente (getenv)</h2>";
echo "<pre>";
var_dump(getenv('MELI_APP_ID'));
echo "</pre>";

echo "<h2>Teste do Loader</h2>";
require_once __DIR__ . '/config/env_loader.php';
echo "<p>Loader executado.</p>";

echo "<pre>";
echo "MELI_APP_ID: " . getenv('MELI_APP_ID') . "\n";
echo "MELI_REDIRECT_URI: " . getenv('MELI_REDIRECT_URI') . "\n";
echo "</pre>";
