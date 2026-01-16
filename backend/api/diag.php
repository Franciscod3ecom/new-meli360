<?php
// backend/api/diag.php
// SYSTEM DOCTOR & DIAGNOSTICS
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');

echo "=== MELI360 SYSTEM DOCTOR ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Environment Check
echo "[1] ENVIRONMENT\n";
echo "PHP Version: " . phpversion() . "\n";
echo "OS: " . PHP_OS . "\n";
$extensions = ['curl', 'pdo', 'pdo_pgsql', 'json', 'mbstring'];
foreach ($extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "  [OK] Extension '$ext' loaded.\n";
    } else {
        echo "  [FAIL] Extension '$ext' NOT loaded!\n";
    }
}

// 2. File Access Check
echo "\n[2] FILE SYSTEM\n";
$paths = [
    '../config/config.php',
    '../config/database.php',
    '../cron/sync_v2.php',
    'migrate_v3.php'
];

foreach ($paths as $path) {
    $real = realpath(__DIR__ . '/' . $path);
    if ($real && file_exists($real)) {
        echo "  [OK] Found: $path\n";
        // Syntax check skipped (exec disabled)
    } else {
        echo "  [FAIL] NOT FOUND: $path\n";
    }
}

// 3. Database Connection Check
echo "\n[3] DATABASE CONNECTION\n";
try {
    if (!file_exists(__DIR__ . '/../config/database.php')) {
        throw new Exception("database.php missing");
    }
    require_once __DIR__ . '/../config/database.php';

    if (!function_exists('getDatabaseConnection')) {
        throw new Exception("Function getDatabaseConnection() not found!");
    }

    $pdo = getDatabaseConnection();
    echo "  [OK] Connected to Database!\n";

    // 4. Schema Check
    echo "\n[4] SCHEMA CHECK (Table: items)\n";
    $stmt = $pdo->query("
        SELECT column_name, data_type 
        FROM information_schema.columns 
        WHERE table_name = 'items'
    ");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($columns)) {
        echo "  [WARN] Table 'items' not found or empty columns.\n";
    } else {
        echo "  [OK] Table 'items' exists. Columns found:\n";
        $foundCols = [];
        foreach ($columns as $col) {
            $foundCols[] = $col['column_name'];
        }

        $required = ['secure_thumbnail', 'health', 'original_price', 'currency_id', 'total_visits', 'last_sale_date'];
        foreach ($required as $req) {
            if (in_array($req, $foundCols)) {
                echo "    [OK] $req exists\n";
            } else {
                echo "    [FAIL] $req MISSING! (Run migrate_v3.php)\n";
            }
        }
    }

} catch (Exception $e) {
    echo "  [FATAL] DB Error: " . $e->getMessage() . "\n";
}

echo "\n=== DIAGNOSTIC COMPLETE ===\n";
