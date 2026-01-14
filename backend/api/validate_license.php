<?php
// backend/api/validate_license.php
session_start();
header('Content-Type: application/json');

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$email = $data['email'] ?? null;
$mlUserId = $data['ml_user_id'] ?? null;

if (!$email || !$mlUserId) {
    http_response_code(400);
    echo json_encode(['error' => 'Email e ML User ID são obrigatórios']);
    exit;
}

// Sanitize email
$email = trim(strtolower($email));

// Google Apps Script API URL
$LICENSE_API_URL = "https://script.google.com/macros/s/AKfycbwPvvNCaufmMZkWu4veRDdxDFPkk16adkod_7xs-kRzOxGTfrkAM-W9HLKLT3WYXkBlbw/exec";

try {
    // Call Google Apps Script
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $LICENSE_API_URL,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'email' => $email,
            'ml_id' => $mlUserId
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 10
    ]);
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    // Fail-Open: If API is down, allow access (don't block legitimate users)
    if ($httpCode !== 200 || !$response) {
        error_log("License API unavailable - Fail-Open activated");
        $_SESSION['license_validated'] = true;
        $_SESSION['license_email'] = $email;
        echo json_encode([
            'autorizado' => true,
            'mensagem' => 'Validação offline ativada'
        ]);
        exit;
    }
    
    $result = json_decode($response, true);
    
    // If authorized, save to session
    if ($result['autorizado'] === true) {
        $_SESSION['license_validated'] = true;
        $_SESSION['license_email'] = $email;
        $_SESSION['license_ml_id'] = $mlUserId;
    }
    
    // Return the result from Google Apps Script
    echo json_encode($result);
    
} catch (Exception $e) {
    // Fail-Open: On error, allow access
    error_log("License validation error: " . $e->getMessage());
    $_SESSION['license_validated'] = true;
    $_SESSION['license_email'] = $email;
    echo json_encode([
        'autorizado' => true,
        'mensagem' => 'Validação offline ativada (erro)'
    ]);
}
