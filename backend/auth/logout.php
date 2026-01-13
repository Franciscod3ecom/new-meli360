<?php
// backend/auth/logout.php
session_start();
session_destroy();

$config = require __DIR__ . '/../config/config.php';
$FRONTEND_URL = $config['FRONTEND_URL'] ?: 'http://localhost:5173'; // Fallback logic

// Redirect to login page
header("Location: $FRONTEND_URL/login");
exit;
