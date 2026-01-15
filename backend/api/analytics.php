<?php
// backend/api/analytics.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
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

    // Get current account ID
    $stmt = $pdo->prepare("SELECT id FROM accounts WHERE ml_user_id = :ml_user_id LIMIT 1");
    $stmt->execute([':ml_user_id' => $_SESSION['user_id']]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$account) {
        http_response_code(404);
        echo json_encode(['error' => 'Account not found']);
        exit;
    }

    $account_id = $account['id'];

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
