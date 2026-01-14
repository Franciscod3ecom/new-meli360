<?php
// backend/api/check_license.php
session_start();
header('Content-Type: application/json');

// Check if license is validated in session
$isValidated = isset($_SESSION['license_validated']) && $_SESSION['license_validated'] === true;

echo json_encode([
    'validated' => $isValidated,
    'email' => $_SESSION['license_email'] ?? null
]);
