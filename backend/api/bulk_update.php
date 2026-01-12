<?php
// backend/api/bulk_update.php

// CORS Headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/ml_api.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$itemIds = $input['item_ids'] ?? [];
$action = $input['action'] ?? null;

if (empty($itemIds) || !in_array($action, ['paused', 'active'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input. Provide item_ids array and action (paused/active).']);
    exit;
}

try {
    $pdo = getDatabaseConnection();
    
    // Get user token (Assuming single tenant for now, or get from header if fully authenticated)
    // For this project phase, we take the latest updated token from accounts table
    $stmt = $pdo->query("SELECT access_token FROM accounts ORDER BY updated_at DESC LIMIT 1");
    $accessToken = $stmt->fetchColumn();

    if (!$accessToken) {
        throw new Exception("No access token found. Please connect your Mercado Livre account.");
    }

    $results = ['success' => 0, 'failed' => 0, 'details' => []];

    foreach ($itemIds as $itemId) {
        // Call ML API
        $response = updateMercadoLibreItemStatus($itemId, $action, $accessToken);

        if ($response['httpCode'] === 200) {
            $results['success']++;
            $results['details'][] = ['id' => $itemId, 'status' => 'success'];
            
            // Update local DB
            $updateStmt = $pdo->prepare("UPDATE items SET status = ? WHERE ml_id = ?");
            $updateStmt->execute([$action, $itemId]); // 'active' or 'paused' matches DB schema
        } else {
            $results['failed']++;
            $results['details'][] = ['id' => $itemId, 'status' => 'failed', 'error' => $response['response']];
        }
        
        // Rate limiting precaution
        usleep(200000); // 200ms
    }

    echo json_encode($results);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
