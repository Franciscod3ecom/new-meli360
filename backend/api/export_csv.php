<?php
/**
 * Arquivo: export_csv.php
 * Descrição: Exporta os itens do inventário em formato CSV
 */

session_start();

require_once __DIR__ . '/../config/database.php';

// Verificar sessão ativa
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../frontend/index.html?error=unauthorized');
    exit;
}

$ml_user_id = $_SESSION['user_id'];

try {
    $pdo = getDatabaseConnection();

    // Determinar qual conta (account_id UUID) usar
    $account_id = null;

    // Prioridade 1: Conta selecionada na sessão (Login Nativo ou Switch Account)
    if (!empty($_SESSION['selected_account_id'])) {
        $account_id = $_SESSION['selected_account_id'];
    }
    // Prioridade 2: Fallback para Login OAuth antigo (SESSION['user_id'] = ml_user_id)
    else {
        // Verifica se é login nativo sem conta selecionada
        if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'native') {
            // Usuário nativo precisa ter selecionado a conta via me.php ou switch_account
            // Se chegou aqui sem selected_account_id, vamos tentar pegar a primeira vinculada como fallback
            $stmt = $pdo->prepare("
                SELECT a.id 
                FROM accounts a
                JOIN user_accounts ua ON a.id = ua.account_id
                WHERE ua.user_id = :user_id
                ORDER BY a.nickname LIMIT 1
           ");
            $stmt->execute([':user_id' => $_SESSION['user_id']]);
            $acc = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($acc) {
                $account_id = $acc['id'];
                $_SESSION['selected_account_id'] = $account_id; // Salva para próximas
            }
        } else {
            // É Login OAuth (onde user_id é o ml_user_id)
            $stmt = $pdo->prepare("SELECT id FROM accounts WHERE ml_user_id = :id LIMIT 1");
            $stmt->execute([':id' => $_SESSION['user_id']]);
            $acc = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($acc) {
                $account_id = $acc['id'];
            }
        }
    }

    if (!$account_id) {
        die("Erro: Nenhuma conta selecionada. Por favor, conecte uma conta no painel.");
    }

    // Buscar todos os itens (respeitando filtros se houver)
    $sql = "SELECT 
                ml_id,
                title,
                status,
                available_quantity as estoque,
                sold_quantity as vendas,
                total_visits as visitas,
                date_created as criacao,
                last_sale_date as ultima_venda,
                price as preco,
                shipping_mode as tipo_envio,
                free_shipping as frete_gratis,
                health as saude,
                category_name as categoria,
                billable_weight as peso_faturavel,
                weight_status as status_peso,
                shipping_cost_nacional as custo_frete
            FROM items 
            WHERE account_id = :account_id 
            ORDER BY sold_quantity DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':account_id' => $account_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Configurar headers para download CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="meli360_inventario_' . date('Y-m-d_His') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Abrir output stream
    $output = fopen('php://output', 'w');

    // Adicionar BOM para UTF-8 (Excel compatibility)
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Escrever cabeçalhos
    $headers = [
        'ID ML',
        'Título',
        'Status',
        'Estoque',
        'Vendas',
        'Visitas',
        'Data Criação',
        'Última Venda',
        'Preço',
        'Tipo Envio',
        'Frete Grátis',
        'Saúde (%)',
        'Categoria',
        'Peso Faturável (g)',
        'Status Peso',
        'Custo Frete (R$)'
    ];
    fputcsv($output, $headers, ';');

    // Escrever dados
    foreach ($items as $item) {
        $row = [
            $item['ml_id'],
            $item['title'],
            $item['status'],
            $item['estoque'],
            $item['vendas'],
            $item['visitas'],
            $item['criacao'] ? date('d/m/Y', strtotime($item['criacao'])) : '',
            $item['ultima_venda'] ? date('d/m/Y', strtotime($item['ultima_venda'])) : '',
            number_format($item['preco'], 2, ',', '.'),
            $item['tipo_envio'],
            $item['frete_gratis'] ? 'Sim' : 'Não',
            $item['saude'] ? round($item['saude'] * 100) : '',
            $item['categoria'],
            $item['peso_faturavel'],
            $item['status_peso'],
            $item['custo_frete'] ? number_format($item['custo_frete'], 2, ',', '.') : ''
        ];
        fputcsv($output, $row, ';');
    }

    fclose($output);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo "Erro ao exportar CSV: " . $e->getMessage();
}
?>