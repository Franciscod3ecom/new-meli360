<?php
/**
 * Arquivo: index.php (Analisador de Anúncios ML)
 * Descrição: Página inicial/landing page.
 */
require_once __DIR__ . '/config.php';

if (isset($_SESSION['saas_user_id'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analisador de Anúncios ML</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="style.css">
</head>
<body class="bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-200 flex flex-col min-h-screen">
    <main class="main-content flex items-center justify-center">
        <div class="max-w-xl w-full space-y-8 text-center p-8">
            <h1 class="text-4xl font-extrabold text-gray-900 dark:text-white sm:text-5xl">
                📈 Analisador de Anúncios ML
            </h1>
            <p class="mt-4 text-xl text-gray-500 dark:text-gray-400">
                Obtenha insights valiosos sobre seus anúncios do Mercado Livre para otimizar suas vendas.
            </p>
            <div class="mt-10 flex flex-col sm:flex-row sm:justify-center space-y-4 sm:space-y-0 sm:space-x-4">
                <a href="login.php" class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-lg shadow-sm text-white bg-blue-600 hover:bg-blue-700">
                    Acessar Painel
                </a>
                <a href="register.php" class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-lg text-blue-700 bg-blue-100 hover:bg-blue-200">
                    Criar Conta
                </a>
            </div>
        </div>
    </main>
    <footer class="py-6 text-center">
        <p class="text-sm text-gray-500 dark:text-gray-400">© <?php echo date('Y'); ?></p>
    </footer>
</body>
</html>