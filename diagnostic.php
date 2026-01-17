<?php
/**
 * Diagnostic Script for 500 Error
 * This will help identify what's causing the error
 */

// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<h1>Diagnostic Check</h1>";
echo "<pre>";

// Test 1: Check if db_config.php exists and can be loaded
echo "\n=== Test 1: Loading db_config.php ===\n";
try {
    if (file_exists('includes/db_config.php')) {
        echo "✓ db_config.php exists\n";
        require_once 'includes/db_config.php';
        echo "✓ db_config.php loaded successfully\n";
    } else {
        echo "✗ db_config.php NOT FOUND\n";
        die();
    }
} catch (Exception $e) {
    echo "✗ Error loading db_config.php: " . $e->getMessage() . "\n";
    die();
}

// Test 2: Test database connection
echo "\n=== Test 2: Database Connection ===\n";
try {
    $conn = getDbConnection();
    if ($conn) {
        echo "✓ Database connection successful\n";
        echo "  Server: " . $conn->server_info . "\n";
        echo "  Database: " . DB_NAME . "\n";
    }
} catch (Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n";
    die();
}

// Test 3: Check if db.php can be loaded
echo "\n=== Test 3: Loading db.php ===\n";
try {
    if (file_exists('includes/db.php')) {
        echo "✓ db.php exists\n";
        require_once 'includes/db.php';
        echo "✓ db.php loaded successfully\n";
    } else {
        echo "✗ db.php NOT FOUND\n";
        die();
    }
} catch (Exception $e) {
    echo "✗ Error loading db.php: " . $e->getMessage() . "\n";
    echo "  Error details: " . $e->getFile() . " on line " . $e->getLine() . "\n";
    die();
}

// Test 4: Check critical tables exist
echo "\n=== Test 4: Checking Database Tables ===\n";
$tables = ['users', 'models', 'categories', 'printers', 'filaments', 'print_profiles'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "✓ Table '$table' exists\n";
    } else {
        echo "✗ Table '$table' NOT FOUND\n";
    }
}

// Test 5: Try calling a database function
echo "\n=== Test 5: Testing Database Functions ===\n";
try {
    if (function_exists('getAllModels')) {
        echo "✓ getAllModels function exists\n";
        $models = getAllModels(1);
        echo "✓ getAllModels executed successfully\n";
        echo "  Returned " . (is_array($models) ? count($models['models']) : 0) . " models\n";
    } else {
        echo "✗ getAllModels function NOT FOUND\n";
    }
} catch (Exception $e) {
    echo "✗ Error calling getAllModels: " . $e->getMessage() . "\n";
}

// Test 6: Try loading profile_parser.php
echo "\n=== Test 6: Loading profile_parser.php ===\n";
try {
    if (file_exists('includes/profile_parser.php')) {
        echo "✓ profile_parser.php exists\n";
        require_once 'includes/profile_parser.php';
        echo "✓ profile_parser.php loaded successfully\n";
    } else {
        echo "✗ profile_parser.php NOT FOUND\n";
    }
} catch (Exception $e) {
    echo "✗ Error loading profile_parser.php: " . $e->getMessage() . "\n";
    echo "  Error details: " . $e->getFile() . " on line " . $e->getLine() . "\n";
}

// Test 7: Check if new database functions exist
echo "\n=== Test 7: Checking New Profile Functions ===\n";
$functions = ['getPrinters', 'getFilaments', 'getPrintProfiles'];
foreach ($functions as $func) {
    if (function_exists($func)) {
        echo "✓ Function '$func' exists\n";
    } else {
        echo "✗ Function '$func' NOT FOUND\n";
    }
}

// Test 8: Try calling new functions
echo "\n=== Test 8: Testing New Functions ===\n";
try {
    $printers = getPrinters();
    echo "✓ getPrinters() executed successfully\n";
    echo "  Returned " . (is_array($printers) ? count($printers) : 0) . " printers\n";

    $filaments = getFilaments();
    echo "✓ getFilaments() executed successfully\n";
    echo "  Returned " . (is_array($filaments) ? count($filaments) : 0) . " filaments\n";
} catch (Exception $e) {
    echo "✗ Error calling new functions: " . $e->getMessage() . "\n";
}

echo "\n=== Diagnostic Complete ===\n";
echo "If all tests passed, the issue may be in a specific page.\n";
echo "Check your web server error log for more details.\n";
echo "</pre>";
?>
