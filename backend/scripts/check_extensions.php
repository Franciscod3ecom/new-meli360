<?php
// backend/scripts/check_extensions.php

echo "PHP Version: " . phpversion() . "\n";
echo "Loaded Extensions:\n";
$exts = get_loaded_extensions();
foreach ($exts as $ext) {
    if (strpos($ext, 'pdo') !== false || strpos($ext, 'pgsql') !== false) {
        echo " - " . $ext . "\n";
    }
}

if (!in_array('pdo_pgsql', $exts)) {
    echo "\nERROR: pdo_pgsql extension is NOT loaded!\n";
} else {
    echo "\nOK: pdo_pgsql is loaded.\n";
}
