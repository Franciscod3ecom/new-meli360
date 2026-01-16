<?php
/**
 * Arquivo: dashboard.php
 * Versão: v9.3 - Adiciona filtro de tempo sem venda e ação de pausar em massa.
 * Descrição: Painel de controle principal do Analisador de Anúncios ML.
 */

// --- Includes Essenciais ---
require_once __DIR__ . '/config.php'; // Inclui config, que por sua vez já inclui os helpers.
require_once __DIR__ . '/db.php';

// --- Proteção de Acesso e Lógica de Personificação ---
if (!isset($_SESSION['saas_user_id'])) { header('Location: login.php?error=unauthorized'); exit; }
$isImpersonating = isset($_SESSION['impersonating_user_id']);
$saasUserIdToQuery = $isImpersonating ? $_SESSION['impersonating_user_id'] : $_SESSION['saas_user_id'];
$loggedInUserEmail = $_SESSION['saas_user_email'] ?? 'Usuário';
$isSuperAdmin = $_SESSION['is_super_admin'] ?? false;

// --- Configurações de Paginação ---
$itemsPerPageOptions = [100, 200, 500];
$itemsPerPage = (isset($_GET['limit']) && in_array((int)$_GET['limit'], $itemsPerPageOptions)) ? (int)$_GET['limit'] : 100;
$currentPage = isset($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;

// --- Lógica de Filtros ---
$validStatusFilters = ['all', 'active', 'paused', 'no_stock', 'closed'];
$filterStatus = (isset($_GET['filter_status']) && in_array($_GET['filter_status'], $validStatusFilters)) ? $_GET['filter_status'] : 'all';

$validSaleFilters = ['all', 'never_sold', 'over_30', 'over_60', 'over_90'];
$filterSales = (isset($_GET['filter_sales']) && in_array($_GET['filter_sales'], $validSaleFilters)) ? $_GET['filter_sales'] : 'all';

// --- Inicialização de Variáveis ---
$mlConnection = null;
$anuncios = [];
$totalAnunciosNoDb = 0;
$totalPages = 1;
$dashboardMessage = null;
$dashboardMessageClass = '';

try {
    $pdo = getDbConnection();
    $stmtML = $pdo->prepare("SELECT * FROM mercadolibre_users WHERE saas_user_id = ?");
    $stmtML->execute([$saasUserIdToQuery]);
    $mlConnection = $stmtML->fetch(PDO::FETCH_ASSOC);

    if ($mlConnection) {
        $baseSql = "FROM anuncios WHERE saas_user_id = :saas_user_id";
        $params = ['saas_user_id' => $saasUserIdToQuery];

        // Aplica filtro de STATUS
        switch ($filterStatus) {
            case 'active': $baseSql .= " AND ml_status = 'active'"; break;
            case 'paused': $baseSql .= " AND ml_status = 'paused'"; break;
            case 'closed': $baseSql .= " AND ml_status = 'closed'"; break;
            case 'no_stock': $baseSql .= " AND available_quantity = 0"; break; // BUG CORRIGIDO
        }

        // Aplica filtro de TEMPO SEM VENDA
        switch ($filterSales) {
            case 'never_sold': $baseSql .= " AND total_sales = 0 AND ml_status != 'closed'"; break;
            case 'over_30': $baseSql .= " AND total_sales > 0 AND last_sale_date < DATE_SUB(NOW(), INTERVAL 30 DAY)"; break;
            case 'over_60': $baseSql .= " AND total_sales > 0 AND last_sale_date < DATE_SUB(NOW(), INTERVAL 60 DAY)"; break;
            case 'over_90': $baseSql .= " AND total_sales > 0 AND last_sale_date < DATE_SUB(NOW(), INTERVAL 90 DAY)"; break;
        }

        // Conta os resultados com os filtros aplicados
        $countStmt = $pdo->prepare("SELECT COUNT(*) " . $baseSql);
        $countStmt->execute($params);
        $totalAnunciosNoDb = (int) $countStmt->fetchColumn();
        
        $totalPages = ($totalAnunciosNoDb > 0) ? ceil($totalAnunciosNoDb / $itemsPerPage) : 1;
        if ($currentPage > $totalPages) $currentPage = $totalPages;
        $offset = ($currentPage - 1) * $itemsPerPage;

        // Busca os anúncios para a página atual com filtros
        $stmtAnuncios = $pdo->prepare("SELECT * " . $baseSql . " ORDER BY total_sales DESC LIMIT :limit OFFSET :offset");
        foreach ($params as $key => $value) { $stmtAnuncios->bindValue($key, $value); }
        $stmtAnuncios->bindParam(':limit', $itemsPerPage, PDO::PARAM_INT);
        $stmtAnuncios->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmtAnuncios->execute();
        $anuncios = $stmtAnuncios->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    logMessage("Erro DB Dashboard v9.3: " . $e->getMessage());
    $dashboardMessage = ['type' => 'is-danger', 'text' => '⚠️ Erro ao carregar dados do dashboard.'];
}

// --- Tratamento de Mensagens de Status da URL ---
$message_classes = [ 'is-success' => 'bg-green-100 text-green-800', 'is-danger' => 'bg-red-100 text-red-800', 'is-info' => 'bg-blue-100 text-blue-800', ];
if (isset($_GET['status'])) {
    $status_param = $_GET['status'];
    if ($status_param === 'ml_connected') { $dashboardMessage = ['type' => 'is-success', 'text' => '✅ Conta Mercado Livre conectada! Solicite a sincronização dos anúncios abaixo.']; }
    if ($status_param === 'sync_requested') { $dashboardMessage = ['type' => 'is-info', 'text' => 'ℹ️ Sincronização solicitada! O processo ocorrerá em segundo plano. Atualize a página em alguns minutos para ver o progresso.']; }
    if ($status_param === 'ml_disconnected') { $dashboardMessage = ['type' => 'is-success', 'text' => '✅ Conta Mercado Livre desconectada com sucesso.']; }
    if ($status_param === 'disconnect_denied') { $dashboardMessage = ['type' => 'is-danger', 'text' => '❌ Ação de desconectar não é permitida durante a personificação.']; }
    if ($status_param === 'sync_error' || $status_param === 'disconnect_error') { $dashboardMessage = ['type' => 'is-danger', 'text' => '❌ Ocorreu um erro ao processar sua solicitação.']; }
    if ($status_param === 'ml_error') { $code = $_GET['code'] ?? 'unknown'; $dashboardMessage = ['type' => 'is-danger', 'text' => "❌ Erro ao conectar com Mercado Livre (Código: $code). Tente novamente."]; }
}
if ($dashboardMessage && isset($message_classes[$dashboardMessage['type']])) { $dashboardMessageClass = $message_classes[$dashboardMessage['type']]; }
if (isset($_GET['status'])){ echo "<script> if (history.replaceState) { setTimeout(function() { var url = new URL(window.location); url.searchParams.delete('status'); url.searchParams.delete('code'); window.history.replaceState({path:url.href}, '', url.href); }, 1); } </script>"; }

// Função auxiliar para gerar URLs de filtro mantendo outros filtros
function buildFilterUrl($params) {
    global $filterStatus, $filterSales, $itemsPerPage;
    $currentParams = [
        'filter_status' => $filterStatus,
        'filter_sales' => $filterSales,
        'limit' => $itemsPerPage,
        'page' => 1 // Sempre reseta para a primeira página ao mudar um filtro
    ];
    return '?' . http_build_query(array_merge($currentParams, $params));
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Analisador ML</title>
    <?php if ($mlConnection && $mlConnection['sync_status'] === 'SYNCING'): ?>
    <meta http-equiv="refresh" content="30">
    <?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="bg-gray-100 text-gray-900">

    <!-- BARRA DE PERSONIFICAÇÃO -->
    <?php if ($isImpersonating): ?>
        <div class="bg-yellow-400 text-black text-center p-2 font-bold sticky top-0 z-50">
            <p>⚠️ Você está vendo como <strong><?php echo htmlspecialchars($impersonatedUserEmail); ?></strong>. 
               <a href="stop_impersonating.php" class="underline hover:text-blue-800">Retornar ao Painel Admin</a></p>
        </div>
    <?php endif; ?>

    <section class="container mx-auto px-4 py-8">
        <header class="bg-white shadow rounded-lg p-4 mb-6 flex justify-between items-center">
            <h1 class="text-xl font-semibold">📈 Analisador de Anúncios ML</h1>
            <div>
                <span class="text-sm text-gray-600 mr-4">Olá, <?php echo htmlspecialchars($loggedInUserEmail); ?></span>
                <?php if ($isSuperAdmin && !$isImpersonating): ?>
                    <a href="super_admin.php" class="text-sm text-purple-600 hover:underline mr-4">Painel Admin</a>
                <?php endif; ?>
                <a href="logout.php" class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded shadow-sm text-white bg-red-600 hover:bg-red-700">Sair</a>
            </div>
        </header>

        <?php if ($dashboardMessage): ?><div id="dashboard-message" class="p-4 mb-6 text-sm rounded-lg <?php echo $dashboardMessageClass; ?>" role="alert"><?php echo htmlspecialchars($dashboardMessage['text']); ?></div><?php endif; ?>
        <div id="ajax-message-container"></div>

        <div class="bg-white shadow rounded-lg p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">🔗 Conexão e Sincronização</h2>
            <?php if ($mlConnection): ?>
                <div class="space-y-3 mb-4 text-sm">
                    <div><span class="font-medium text-gray-600">ID Vendedor ML:</span> <span class="ml-2 font-mono"><?php echo htmlspecialchars($mlConnection['ml_user_id']); ?></span></div>
                    <?php if ($mlConnection['sync_status'] === 'SYNCING'): ?>
                        <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                            <div class="flex items-center space-x-2 mb-2"><svg class="animate-spin h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg><span class="font-bold text-blue-800">Sincronizando...</span></div>
                            <p class="text-xs text-blue-700 mb-2"><?php echo htmlspecialchars($mlConnection['sync_last_message']); ?></p>
                            <div class="w-full bg-gray-200 rounded-full h-2.5"><div class="bg-blue-600 h-2.5 rounded-full" style="width: <?php echo $mlConnection['sync_status'] === 'SYNCING' && preg_match('/(\d+) de (\d+)/', $mlConnection['sync_last_message'] ?? '', $m) ? round(((int)$m[1] / (int)$m[2]) * 100) : 0; ?>%"></div></div>
                        </div>
                    <?php else: ?>
                        <div><span class="font-medium text-gray-600">Status da Sincronização:</span> <span class="ml-2 font-mono font-bold"><?php echo htmlspecialchars($mlConnection['sync_status']); ?></span></div>
                        <?php if ($mlConnection['sync_last_message']): ?><div class="text-xs text-gray-500 italic">Mensagem: <?php echo htmlspecialchars($mlConnection['sync_last_message']); ?></div><?php endif; ?>
                    <?php endif; ?>
                </div>

                <div class="flex items-center space-x-4 mt-4">
                    <?php if (in_array($mlConnection['sync_status'], ['IDLE', 'COMPLETED', 'ERROR'])): ?>
                        <a href="request_sync.php" onclick="return confirm('ATENÇÃO:\nIsso limpará todos os dados de anúncios salvos no sistema e iniciará uma nova sincronização do zero.\n\nEsta ação não afeta seus anúncios no Mercado Livre.\n\nDeseja continuar?');" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">🔄 Limpar e Sincronizar Tudo</a>
                    <?php elseif ($mlConnection['sync_status'] === 'PAUSED'): ?>
                        <a href="toggle_sync.php?action=resume" class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-md hover:bg-green-700">▶️ Retomar</a>
                    <?php elseif (in_array($mlConnection['sync_status'], ['REQUESTED', 'SYNCING'])): ?>
                        <a href="toggle_sync.php?action=pause" class="px-4 py-2 text-sm font-medium text-white bg-yellow-500 rounded-md hover:bg-yellow-600">⏸️ Pausar</a>
                    <?php endif; ?>
                </div>

                <div class="border-t border-gray-200 mt-6 pt-4">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase mb-3">Gerenciar Conexão</h3>
                    <div class="flex items-center space-x-4">
                        <a href="oauth_start.php" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200">Reconectar Conta</a>
                        <form action="disconnect_ml.php" method="POST" onsubmit="return confirm('Tem certeza que deseja desconectar sua conta do Mercado Livre?\n\nTODOS os seus dados de anúncios sincronizados serão APAGADOS do nosso sistema.');"><button type="submit" class="px-4 py-2 text-sm font-medium text-red-700 bg-red-100 border border-red-200 rounded-md hover:bg-red-200">Desconectar</button></form>
                    </div>
                </div>
            <?php else: ?>
                 <p class="mb-4 text-sm">Conecte sua conta do Mercado Livre para começar.</p><a href="oauth_start.php" class="inline-flex items-center px-4 py-2 border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">🔗 Conectar Conta</a>
            <?php endif; ?>
        </div>

        <form id="anuncios-form" action="update_selected.php" method="POST">
            <div class="bg-white shadow rounded-lg p-6">
                <div class="flex flex-wrap justify-between items-center gap-4 mb-4">
                    <h2 class="text-lg font-semibold">📊 Seus Anúncios (<?php echo $totalAnunciosNoDb; ?>)</h2>
                    <div class="flex items-center space-x-2">
                        <button type="button" id="bulk-pause-btn" class="px-3 py-1.5 text-sm font-medium text-white bg-orange-500 rounded-md hover:bg-orange-600 disabled:opacity-50"><i class="fa-solid fa-pause mr-1"></i> Pausar Selecionados</button>
                        <button type="submit" class="px-3 py-1.5 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700">Atualizar Selecionados</button>
                        <a href="download_csv.php" class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-sm font-medium rounded-md">📥 Baixar CSV</a>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4 pb-4 border-b border-gray-200">
                    <div>
                        <label class="text-sm font-medium text-gray-600">Filtrar por Status:</label>
                        <div class="flex flex-wrap items-center gap-2 mt-1">
                            <?php $statusLabels = ['all' => 'Todos', 'active' => 'Ativos', 'paused' => 'Pausados', 'no_stock' => 'Sem Estoque', 'closed' => 'Finalizados']; ?>
                            <?php foreach ($statusLabels as $key => $label): ?>
                                <a href="<?php echo buildFilterUrl(['filter_status' => $key]); ?>" class="px-3 py-1 text-sm font-medium rounded-full border <?php echo ($filterStatus === $key) ? 'bg-blue-600 text-white border-blue-700' : 'bg-white text-gray-700 hover:bg-gray-50 border-gray-300'; ?>"><?php echo $label; ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div>
                        <label for="filter_sales" class="text-sm font-medium text-gray-600">Filtrar por Tempo sem Venda:</label>
                        <select id="filter_sales" onchange="window.location.href = this.value;" class="mt-1 block w-full p-2 border-gray-300 rounded-md shadow-sm">
                            <?php $salesLabels = ['all' => 'Qualquer período', 'never_sold' => 'Nunca Vendeu', 'over_30' => 'Sem venda há +30 dias', 'over_60' => 'Sem venda há +60 dias', 'over_90' => 'Sem venda há +90 dias']; ?>
                            <?php foreach ($salesLabels as $key => $label): ?>
                                <option value="<?php echo buildFilterUrl(['filter_sales' => $key]); ?>" <?php if ($filterSales === $key) echo 'selected'; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="flex flex-wrap justify-between items-center gap-4 mb-4">
                    <div class="flex items-center space-x-2 text-sm"><span>Exibir</span><select name="limit" id="limit-select" class="p-1 rounded-md border-gray-300" onchange="window.location.href = '<?php echo buildFilterUrl([]) . '&page=1&limit='; ?>' + this.value;"><?php foreach($itemsPerPageOptions as $option): ?><option value="<?php echo $option; ?>" <?php if($itemsPerPage == $option) echo 'selected'; ?>><?php echo $option; ?></option><?php endforeach; ?></select><span>por página</span></div>
                    <?php if ($totalPages > 1): ?><?php $pageUrl = buildFilterUrl([]); ?><div class="flex items-center space-x-1 text-sm"><a href="<?php echo $pageUrl . '&page=1'; ?>" class="px-2 py-1 rounded <?php echo $currentPage == 1 ? 'bg-gray-200 text-gray-500 cursor-not-allowed' : 'bg-gray-100 hover:bg-gray-200'; ?>">«</a><a href="<?php echo $pageUrl . '&page=' . max(1, $currentPage - 1); ?>" class="px-2 py-1 rounded <?php echo $currentPage == 1 ? 'bg-gray-200 text-gray-500 cursor-not-allowed' : 'bg-gray-100 hover:bg-gray-200'; ?>">‹</a><span class="px-3 py-1">Página <?php echo $currentPage; ?> de <?php echo $totalPages; ?></span><a href="<?php echo $pageUrl . '&page=' . min($totalPages, $currentPage + 1); ?>" class="px-2 py-1 rounded <?php echo $currentPage >= $totalPages ? 'bg-gray-200 text-gray-500 cursor-not-allowed' : 'bg-gray-100 hover:bg-gray-200'; ?>">›</a><a href="<?php echo $pageUrl . '&page=' . $totalPages; ?>" class="px-2 py-1 rounded <?php echo $currentPage >= $totalPages ? 'bg-gray-200 text-gray-500 cursor-not-allowed' : 'bg-gray-100 hover:bg-gray-200'; ?>">»</a></div><?php endif; ?>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 w-12"><input type="checkbox" id="select-all" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"></th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase">Anúncio</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase">Estoque</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase">Criação</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase">Visitas</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase">Vendas</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase">Última Venda</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase">Tag de Venda</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($anuncios)): ?>
                                <tr><td colspan="9" class="text-center py-10 text-gray-500">Nenhum anúncio para exibir com os filtros atuais.</td></tr>
                            <?php else: ?>
                                <?php foreach ($anuncios as $anuncio): ?>
                                    <?php $tagInfo = formatLastSaleTag($anuncio['last_sale_date'], (int)$anuncio['total_sales'], (bool)$anuncio['is_synced']); ?>
                                    <tr class="<?php if ($anuncio['is_synced'] == 2) echo 'bg-blue-50'; ?>">
                                        <td class="px-4 py-2"><input type="checkbox" name="selected_ids[]" value="<?php echo $anuncio['ml_item_id']; ?>" class="item-checkbox h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"></td>
                                        <td class="px-4 py-2 whitespace-nowrap"><div class="text-sm font-medium truncate max-w-xs" title="<?php echo htmlspecialchars($anuncio['title'] ?? '...'); ?>"><?php echo htmlspecialchars($anuncio['title'] ?? 'Carregando...'); ?></div><div class="text-xs text-gray-500"><?php echo htmlspecialchars($anuncio['ml_item_id']); ?></div></td>
                                        <td class="px-4 py-2 text-sm"><span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php switch($anuncio['ml_status']) { case 'active': echo 'bg-green-100 text-green-800'; break; case 'paused': echo 'bg-yellow-100 text-yellow-800'; break; case 'closed': echo 'bg-red-100 text-red-800'; break; default: echo 'bg-gray-100 text-gray-800'; } ?>"><?php echo ucfirst(htmlspecialchars($anuncio['ml_status'])); ?></span></td>
                                        <td class="px-4 py-2 text-sm font-mono text-center"><?php echo $anuncio['is_synced'] ? number_format($anuncio['available_quantity']) : '...'; ?></td>
                                        <td class="px-4 py-2 text-sm"><?php echo $anuncio['date_created'] ? date('d/m/Y', strtotime($anuncio['date_created'])) : '...'; ?></td>
                                        <td class="px-4 py-2 text-sm"><?php echo $anuncio['is_synced'] ? number_format($anuncio['total_visits']) : '...'; ?></td>
                                        <td class="px-4 py-2 text-sm"><?php echo $anuncio['is_synced'] ? number_format($anuncio['total_sales']) : '...'; ?></td>
                                        <td class="px-4 py-2 text-sm"><?php echo $anuncio['last_sale_date'] ? date('d/m/Y', strtotime($anuncio['last_sale_date'])) : ($anuncio['is_synced'] ? '-' : '...'); ?></td>
                                        <td class="px-4 py-2 text-sm"><?php if (!empty($tagInfo['text'])): ?><span class="px-2 py-0.5 text-xs font-medium rounded-full <?php echo $tagInfo['class']; ?>"><?php echo $tagInfo['text']; ?></span><?php endif; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </form>
    </section>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const selectAll = document.getElementById('select-all');
        const itemCheckboxes = document.querySelectorAll('.item-checkbox');
        const bulkPauseBtn = document.getElementById('bulk-pause-btn');
        const msgContainer = document.getElementById('ajax-message-container');

        if (selectAll) {
            selectAll.addEventListener('change', function(e) {
                itemCheckboxes.forEach(cb => cb.checked = e.target.checked);
            });
        }

        if (bulkPauseBtn) {
            bulkPauseBtn.addEventListener('click', async function() {
                const selectedIds = Array.from(itemCheckboxes)
                                        .filter(cb => cb.checked)
                                        .map(cb => cb.value);

                if (selectedIds.length === 0) {
                    alert('Por favor, selecione pelo menos um anúncio para pausar.');
                    return;
                }

                if (!confirm(`Tem certeza que deseja PAUSAR ${selectedIds.length} anúncio(s) no Mercado Livre?`)) {
                    return;
                }

                this.disabled = true;
                this.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Pausando...';
                
                const formData = new FormData();
                formData.append('action', 'bulk_pause');
                selectedIds.forEach(id => formData.append('item_ids[]', id));

                try {
                    const response = await fetch('ajax_bulk_actions.php', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();
                    
                    if (response.ok && result.success) {
                        const successCount = result.data.success;
                        const failedCount = result.data.failed;
                        showMessage(`Operação concluída. Anúncios pausados: ${successCount}. Falhas: ${failedCount}. A página será atualizada em breve.`, 'success');
                        setTimeout(() => window.location.reload(), 4000);
                    } else {
                        throw new Error(result.message || 'Ocorreu um erro desconhecido.');
                    }
                } catch (error) {
                    showMessage('Erro na requisição: ' + error.message, 'error');
                } finally {
                    this.disabled = false;
                    this.innerHTML = '<i class="fa-solid fa-pause mr-1"></i> Pausar Selecionados';
                }
            });
        }

        function showMessage(message, type = 'success') {
            const bgColor = type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
            const msgDiv = document.createElement('div');
            msgDiv.className = `p-4 mb-4 text-sm rounded-lg ${bgColor}`;
            msgDiv.setAttribute('role', 'alert');
            msgDiv.textContent = message;
            msgContainer.innerHTML = ''; 
            msgContainer.appendChild(msgDiv);
            
            document.getElementById('dashboard-message')?.remove();

            setTimeout(() => {
                msgDiv.style.transition = 'opacity 0.5s ease';
                msgDiv.style.opacity = '0';
                setTimeout(() => msgDiv.remove(), 500);
            }, 6000);
        }
    });
    </script>
</body>
</html>