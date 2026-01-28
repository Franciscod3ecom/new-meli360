<?php
// backend/api/check_license.php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://d3ecom.com.br');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Check if license is validated in session
    $isValidated = isset($_SESSION['license_validated']) && $_SESSION['license_validated'] === true;

    echo json_encode([
        'validated' => $isValidated,
        'email' => $_SESSION['license_email'] ?? null
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'validated' => false,
        'error' => 'Internal error'
    ]);
}
