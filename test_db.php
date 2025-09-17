<?php
try {
    $pdo = new PDO('pgsql:host=scorecardtrakker_postgres_database;port=5432;dbname=scorecardtrakker', 'scorecardtrakker_user', 'securepassword');
    echo "Database connection successful!\n";
    
    // Test a simple query
    $stmt = $pdo->query('SELECT version()');
    $version = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "PostgreSQL version: " . $version['version'] . "\n";
    
} catch (Exception $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
}
?>
