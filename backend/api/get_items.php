<?php
/**
 * Arquivo: get_items.php
 * Descrição: Retorna items com paginação e filtros
 */

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();

require_once __DIR__ . '/../config/database.php';

// Verificar sessão ativa
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

$ml_user_id = $_SESSION['user_id'];

try {
    $pdo = getDatabaseConnection();

    // Buscar account_id
    $stmt = $pdo->prepare("SELECT id FROM accounts WHERE ml_user_id = :id LIMIT 1");
    $stmt->execute([':id' => $ml_user_id]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$account) {
        throw new Exception('Conta não encontrada');
    }

    $account_id = $account['id'];

    // Parâmetros de paginação
    $page = isset($_GET['page']) && (int) $_GET['page'] > 0 ? (int) $_GET['page'] : 1;
    $limit = isset($_GET['limit']) && in_array((int) $_GET['limit'], [100, 200, 500]) ? (int) $_GET['limit'] : 100;
    $offset = ($page - 1) * $limit;

    // Filtros
    $statusFilter = $_GET['status_filter'] ?? 'all';
    $salesFilter = $_GET['sales_filter'] ?? 'all';

    // Montar SQL base
    $baseSql = "FROM items WHERE account_id = :account_id";
    $params = ['account_id' => $account_id];

    // Aplicar filtro de status
    switch ($statusFilter) {
        case 'active':
            $baseSql .= " AND status = 'active'";
            break;
        case 'paused':
            $baseSql .= " AND status = 'paused'";
            break;
        case 'closed':
            $baseSql .= " AND status = 'closed'";
            break;
        case 'no_stock':
            $baseSql .= " AND available_quantity = 0";
            break;
    }

    // Aplicar filtro de vendas (tempo sem venda)
    switch ($salesFilter) {
        case 'never_sold':
            $baseSql .= " AND sold_quantity = 0 AND status != 'closed'";
            break;
        case 'over_30':
            $baseSql .= " AND sold_quantity > 0 AND last_sale_date < NOW() - INTERVAL '30 days'";
            break;
        case 'over_60':
            $baseSql .= " AND sold_quantity > 0 AND last_sale_date < NOW() - INTERVAL '60 days'";
            break;
        case 'over_90':
            $baseSql .= " AND sold_quantity > 0 AND last_sale_date < NOW() - INTERVAL '90 days'";
            break;
    }

    // Contar total de resultados
    $countStmt = $pdo->prepare("SELECT COUNT(*) " . $baseSql);
    $countStmt->execute($params);
    $totalItems = (int) $countStmt->fetchColumn();
    $totalPages = ceil($totalItems / $limit);

    // Buscar items da página atual
    $itemsStmt = $pdo->prepare("SELECT * " . $baseSql . " ORDER BY sold_quantity DESC LIMIT :limit OFFSET :offset");
    foreach ($params as $key => $value) {
        $itemsStmt->bindValue($key, $value);
    }
    $itemsStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $itemsStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $itemsStmt->execute();
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $items,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_items' => $totalItems,
            'items_per_page' => $limit
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro no servidor: ' . $e->getMessage()
    ]);
}
?>