<?php
// Database setup script
// This script will create the database and all required tables

// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'chama_db';

try {
    // Create a PDO instance (without specifying database name first)
    $pdo = new PDO("mysql:host=$host", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    
    echo "Connected to MySQL successfully!\n";
    
    // Read the SQL file
    $sql = file_get_contents('database_setup.sql');
    
    if ($sql === false) {
        throw new Exception('Could not read the database_setup.sql file');
    }
    
    // Execute the SQL
    $pdo->exec($sql);
    
    echo "Database and tables created successfully!\n";
    
    // Now connect to the specific database
    $pdo->exec("USE $database");
    
    // Test the connection by checking if tables were created
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "\nTables created:\n";
    foreach ($tables as $table) {
        echo "- $table\n";
    }
    
    echo "\nDatabase setup completed successfully!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Include the DatabaseClass for future use
if (file_exists('DatabaseClass.php')) {
    require_once 'DatabaseClass.php';
    echo "\nDatabaseClass.php loaded successfully.\n";
}
?>