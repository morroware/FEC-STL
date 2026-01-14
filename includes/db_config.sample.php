<?php
/**
 * FEC STL Vault - Database Configuration SAMPLE
 *
 * INSTRUCTIONS:
 * 1. Copy this file to db_config.php
 * 2. Update the database credentials below
 * 3. Do NOT commit db_config.php to git (it's in .gitignore)
 *
 * For cPanel shared hosting:
 * 1. Create a MySQL database in cPanel
 * 2. Create a database user and assign to the database
 * 3. Update the constants below with your credentials
 *
 * Database name format in cPanel: cpaneluser_dbname
 * Database user format in cPanel: cpaneluser_dbuser
 */

// Database credentials - UPDATE THESE FOR YOUR CPANEL ACCOUNT
define('DB_HOST', 'localhost');           // Usually 'localhost' for cPanel
define('DB_NAME', 'fecvault_db');         // Your database name (cpaneluser_dbname)
define('DB_USER', 'fecvault_user');       // Your database user (cpaneluser_dbuser)
define('DB_PASS', 'your_password_here');  // Your database password

// Database charset
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', 'utf8mb4_unicode_ci');

/**
 * Get database connection
 * Returns mysqli connection object
 */
function getDbConnection(): mysqli {
    static $conn = null;

    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

        if ($conn->connect_error) {
            // Log error but don't expose details to users
            error_log("Database connection failed: " . $conn->connect_error);
            die("Database connection error. Please contact the administrator.");
        }

        // Set charset
        $conn->set_charset(DB_CHARSET);
    }

    return $conn;
}

/**
 * Close database connection
 */
function closeDbConnection(): void {
    static $conn = null;
    if ($conn !== null) {
        $conn->close();
        $conn = null;
    }
}

/**
 * Escape string for SQL (backward compatibility helper)
 */
function escapeString(string $str): string {
    $conn = getDbConnection();
    return $conn->real_escape_string($str);
}

/**
 * Execute query and return result
 */
function executeQuery(string $sql): mysqli_result|bool {
    $conn = getDbConnection();
    $result = $conn->query($sql);

    if (!$result) {
        error_log("SQL Error: " . $conn->error . " | Query: " . $sql);
    }

    return $result;
}

/**
 * Prepare statement helper
 */
function prepareStatement(string $sql): mysqli_stmt|false {
    $conn = getDbConnection();
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        error_log("SQL Prepare Error: " . $conn->error . " | Query: " . $sql);
    }

    return $stmt;
}
?>
