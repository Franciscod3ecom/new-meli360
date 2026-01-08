<?php
// backend/config/database.php

function getDatabaseConnection() {
    $host = getenv('SUPABASE_HOST') ?: 'db.supabase.co';
    $port = getenv('SUPABASE_PORT') ?: '5432';
    $dbname = getenv('SUPABASE_DB_NAME') ?: 'postgres';
    $user = getenv('SUPABASE_USER') ?: 'postgres';
    $password = getenv('SUPABASE_PASSWORD') ?: 'password';

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
