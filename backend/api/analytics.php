<?php
// backend/api/analytics.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://d3ecom.com.br');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_start();

require_once __DIR__ . '/../config/database.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

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
                $_SESSION['selected_account_id'] = $account_id;
            }
        } else {
            // É Login OAuth direto
            $stmt = $pdo->prepare("SELECT id FROM accounts WHERE ml_user_id = :id LIMIT 1");
            $stmt->execute([':id' => $_SESSION['user_id']]);
            $acc = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($acc) {
                $account_id = $acc['id'];
            }
        }
    }

    if (!$account_id) {
        http_response_code(400);
        echo json_encode(['error' => 'No account selected']);
        exit;
    }

    // 1. Overview Stats
    $overview = $pdo->prepare("
        SELECT 
            COUNT(*) as total_items,
            COALESCE(AVG(health), 0) as avg_health,
            COALESCE(SUM(sold_quantity), 0) as total_sales,
            COALESCE(SUM(price * available_quantity), 0) as total_inventory_value,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_items,
            COALESCE(SUM(total_visits), 0) as total_visits,
            COALESCE(AVG(total_visits), 0) as avg_visits,
            COALESCE(AVG(shipping_cost_nacional), 0) as avg_freight,
            COUNT(CASE WHEN free_shipping IS TRUE THEN 1 END) as free_shipping_count
        FROM items 
        WHERE account_id = :account_id
    ");
    $overview->execute([':account_id' => $account_id]);
    $stats = $overview->fetch(PDO::FETCH_ASSOC);

    if (!$stats) {
        $stats = [
            'total_items' => 0,
            'avg_health' => 0,
            'total_sales' => 0,
            'total_inventory_value' => 0,
            'active_items' => 0,
            'total_visits' => 0,
            'avg_visits' => 0,
            'avg_freight' => 0,
            'free_shipping_count' => 0
        ];
    }

    // Calculate conversion rate (defensive)
    $totalVisits = (float) ($stats['total_visits'] ?? 0);
    $totalSales = (float) ($stats['total_sales'] ?? 0);
    $stats['conversion_rate'] = ($totalVisits > 0) ? ($totalSales / $totalVisits) * 100 : 0;

    // 2. Health Distribution
    $healthDist = $pdo->prepare("
        SELECT 
            CASE 
                WHEN health >= 0.9 THEN 'Excelente (90-100%)'
                WHEN health >= 0.7 THEN 'Boa (70-90%)'
                WHEN health >= 0.5 THEN 'Regular (50-70%)'
                ELSE 'Crítica (0-50%)'
            END as health_range,
            COUNT(*) as count
        FROM items 
        WHERE account_id = :account_id AND health IS NOT NULL
        GROUP BY health_range
        ORDER BY MIN(health) DESC
    ");
    $healthDist->execute([':account_id' => $account_id]);
    $healthData = $healthDist->fetchAll(PDO::FETCH_ASSOC);

    // 3. Logistics Breakdown
    $logistics = $pdo->prepare("
        SELECT 
            CASE 
                WHEN logistic_type = 'fulfillment' THEN 'Full'
                WHEN shipping_mode = 'me2' THEN 'ME2'
                ELSE 'Outros'
            END as logistic_category,
            COUNT(*) as count
        FROM items 
        WHERE account_id = :account_id
        GROUP BY logistic_category
    ");
    $logistics->execute([':account_id' => $account_id]);
    $logisticsData = $logistics->fetchAll(PDO::FETCH_ASSOC);

    // 4. Status Distribution
    $statusDist = $pdo->prepare("
        SELECT status, COUNT(*) as count
        FROM items 
        WHERE account_id = :account_id
        GROUP BY status
    ");
    $statusDist->execute([':account_id' => $account_id]);
    $statusData = $statusDist->fetchAll(PDO::FETCH_ASSOC);

    // 5. Regional Freight Averages
    $regionalFreight = $pdo->prepare("
        SELECT 
            COALESCE(AVG(freight_brasilia), 0) as brasilia,
            COALESCE(AVG(freight_sao_paulo), 0) as sao_paulo,
            COALESCE(AVG(freight_salvador), 0) as salvador,
            COALESCE(AVG(freight_manaus), 0) as manaus,
            COALESCE(AVG(freight_porto_alegre), 0) as porto_alegre
        FROM items 
        WHERE account_id = :account_id
    ");
    $regionalFreight->execute([':account_id' => $account_id]);
    $regionalDataArr = $regionalFreight->fetch(PDO::FETCH_ASSOC);

    // Format for chart
    $regionalData = [
        ['name' => 'Brasília', 'value' => (float) $regionalDataArr['brasilia']],
        ['name' => 'São Paulo', 'value' => (float) $regionalDataArr['sao_paulo']],
        ['name' => 'Salvador', 'value' => (float) $regionalDataArr['salvador']],
        ['name' => 'Manaus', 'value' => (float) $regionalDataArr['manaus']],
        ['name' => 'Porto Alegre', 'value' => (float) $regionalDataArr['porto_alegre']],
    ];

    // 6. Sales Recency Distribution
    $recencyDist = $pdo->prepare("
        SELECT 
            CASE 
                WHEN last_sale_date >= NOW() - INTERVAL '7 days' THEN '7 Dias'
                WHEN last_sale_date >= NOW() - INTERVAL '30 days' THEN '30 Dias'
                WHEN last_sale_date IS NOT NULL THEN '+30 Dias'
                ELSE 'Sem Vendas'
            END as recency_range,
            COUNT(*) as count
        FROM items 
        WHERE account_id = :account_id
        GROUP BY recency_range
    ");
    $recencyDist->execute([':account_id' => $account_id]);
    $recencyData = $recencyDist->fetchAll(PDO::FETCH_ASSOC);

    // 6. Top 5 Performers
    $topItems = $pdo->prepare("
        SELECT ml_id, title, sold_quantity, price, secure_thumbnail, health, total_visits
        FROM items 
        WHERE account_id = :account_id
        ORDER BY sold_quantity DESC
        LIMIT 5
    ");
    $topItems->execute([':account_id' => $account_id]);
    $topPerformers = $topItems->fetchAll(PDO::FETCH_ASSOC);

    // Return JSON
    echo json_encode([
        'overview' => $stats,
        'health_distribution' => $healthData,
        'logistics_breakdown' => $logisticsData,
        'status_distribution' => $statusData,
        'recency_distribution' => $recencyData,
        'regional_distribution' => $regionalData,
        'top_performers' => $topPerformers
    ]);

} catch (Exception $e) {
    // Log error to file for debugging
    error_log("[" . date('Y-m-d H:i:s') . "] Analytics Error: " . $e->getMessage() . "\n", 3, __DIR__ . "/error_log.txt");

    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
