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
            AVG(health) as avg_health,
            SUM(sold_quantity) as total_sales,
            SUM(price * available_quantity) as total_inventory_value,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_items
        FROM items 
        WHERE account_id = :account_id
    ");
    $overview->execute([':account_id' => $account_id]);
    $stats = $overview->fetch(PDO::FETCH_ASSOC);

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

    // 5. Top 5 Performers
    $topItems = $pdo->prepare("
        SELECT ml_id, title, sold_quantity, price, secure_thumbnail, health
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
        'top_performers' => $topPerformers
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
