<?php
// db.php - Database connection using PDO

// Database configuration
$host = 'sql210.ezyro.com';
$dbname = 'ezyro_39822869_cms';
$username = 'ezyro_39822869'; 
$password = 'awortwe2000'; 
$charset = 'utf8mb4';

// DSN (Data Source Name)
$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

// PDO options
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // Create PDO instance
    $pdo = new PDO($dsn, $username, $password, $options);
    
    // Optional: Set timezone if needed
    $pdo->exec("SET time_zone = '+00:00';");
    
} catch (PDOException $e) {
    // Handle connection error
    error_log("Database connection failed: " . $e->getMessage());
    
    // Display user-friendly message (hide detailed error in production)
    throw new PDOException('Database connection failed. Please try again later.');
}

// Return the PDO instance
return $pdo;
?>