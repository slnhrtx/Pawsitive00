<?php

$db_host = getenv('DB_HOST') ?: 'localhost'; // Database host (use environment variable or fallback)
$db_name = getenv('DB_NAME') ?: 'pawsitive'; // Database name
$db_user = getenv('DB_USER') ?: 'root'; // Database username
$db_pass = getenv('DB_PASS') ?: ''; // Database password
$db_charset = 'utf8mb4'; // Character encoding

// DSN (Data Source Name) for PDO
$dsn = "mysql:host=$db_host;dbname=$db_name;charset=$db_charset";
// PDO options for production
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on error
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Set default fetch mode to associative array
    PDO::ATTR_EMULATE_PREPARES   => false, // Disable emulated prepared statements (use real prepared statements)
    PDO::ATTR_PERSISTENT         => false, // Persistent connections (set to true if required for performance)
];
try {
    // Create the PDO instance
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    
    // OPTIONAL: For debugging, log successful connection (remove in production)
    // error_log('Database connection successful');
    
} catch (PDOException $e) {
    // Log the error message to a secure log file
    error_log("Database connection error: " . $e->getMessage());
    // Display a generic error message to the user (do not expose sensitive details)
    die("Database connection failed. Please try again later.");
}
?>