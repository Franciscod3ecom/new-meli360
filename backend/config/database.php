<?php
// backend/config/database.php

function getDatabaseConnection() {
    $config = require __DIR__ . '/config.php';

    $host = $config['SUPABASE_HOST'];
    $port = $config['SUPABASE_PORT'];
    $dbname = $config['SUPABASE_DB_NAME'];
    $user = $config['SUPABASE_USER'];
    $password = $config['SUPABASE_PASSWORD'];

    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";

    try {
        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        // In production, log this to a file instead of echoing
        die("Database Connection Error: " . $e->getMessage());
    }
}
