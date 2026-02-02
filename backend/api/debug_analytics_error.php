<?php
// backend/api/debug_analytics_error.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    die("Not authenticated");
}

try {
    $pdo = getDatabaseConnection();
    $account_id = $_SESSION['selected_account_id'] ?? null;

    if (!$account_id) {
        // Fallback resolution
        if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'native') {
            $stmt = $pdo->prepare("SELECT a.id FROM accounts a JOIN user_accounts ua ON a.id = ua.account_id WHERE ua.user_id = :user_id ORDER BY a.nickname LIMIT 1");
            $stmt->execute([':user_id' => $_SESSION['user_id']]);
            $acc = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($acc)
                $account_id = $acc['id'];
        } else {
            $stmt = $pdo->prepare("SELECT id FROM accounts WHERE ml_user_id = :id LIMIT 1");
            $stmt->execute([':id' => $_SESSION['user_id']]);
            $acc = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($acc)
                $account_id = $acc['id'];
        }
    }

    if (!$account_id) {
        die("No account found");
    }

    echo "Account ID: $account_id<br>";

    $sql = "
        SELECT 
            COUNT(*) as total_items,
            AVG(health) as avg_health,
            SUM(sold_quantity) as total_sales,
            SUM(price * available_quantity) as total_inventory_value,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_items,
            SUM(total_visits) as total_visits,
            AVG(total_visits) as avg_visits,
            AVG(shipping_cost_nacional) as avg_freight,
            COUNT(CASE WHEN free_shipping = 1 THEN 1 END) as free_shipping_count
        FROM items 
        WHERE account_id = :account_id
    ";

    echo "Executing Query...<br>";
    $overview = $pdo->prepare($sql);
    $overview->execute([':account_id' => $account_id]);
    $stats = $overview->fetch(PDO::FETCH_ASSOC);

    echo "<pre>";
    print_r($stats);
    echo "</pre>";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
