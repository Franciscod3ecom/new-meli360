<?php
// Temporary debug version to see what's happening
session_start();
header('Content-Type: application/json');

error_reporting(E_ALL);
ini_set('display_errors', '1');

try {
    echo json_encode([
        'debug' => true,
        'session_exists' => isset($_SESSION['user_id']),
        'user_id' => $_SESSION['user_id'] ?? null,
        'email' => $_SESSION['email'] ?? null,
        'user_type' => $_SESSION['user_type'] ?? null,
        'all_session' => $_SESSION
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
}
