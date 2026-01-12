<?php
// backend/auth/login.php

// Configuration
$config = require __DIR__ . '/../config/config.php';

$APP_ID = $config['MELI_APP_ID'];
$REDIRECT_URI = $config['MELI_REDIRECT_URI'];

if (!$APP_ID || !$REDIRECT_URI) {
    die("Configuration Error: MELI_APP_ID or MELI_REDIRECT_URI not set within config/config.php.");
}

// Generate Auth URL
$authUrl = "https://auth.mercadolivre.com.br/authorization?response_type=code&client_id=$APP_ID&redirect_uri=$REDIRECT_URI";

// Redirect User
header("Location: $authUrl");
exit;
