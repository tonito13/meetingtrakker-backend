<?php
/**
 * Script to clear all data from scorecardtrakker_300000 database
 * This will delete all data but keep the table structure intact
 */

try {
    // Connect directly to PostgreSQL database
    // From host machine, use localhost:5433 (Docker maps 5433->5432)
    // From Docker container, use postgres_workmatica_template:5432
    $hosts = [
        ['host' => 'localhost', 'port' => 5433],  // Host machine access
        ['host' => 'postgres_workmatica_template', 'port' => 5432]  // Docker network access
    ];
    $database = 'scorecardtrakker_300000';
    $username = 'workmatica_user';
    $password = 'securepassword';
    
    $pdo = null;
    $lastError = null;
    
    foreach ($hosts as $config) {
        try {
            $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname=$database";
            $pdo = new PDO($dsn, $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            echo "Connected using host: {$config['host']}:{$config['port']}\n";
            break;
        } catch (PDOException $e) {
            $lastError = $e;
            continue;
        }
    }
    
    if (!$pdo) {
        throw new Exception("Failed to connect to database. Last error: " . $lastError->getMessage());
    }
    
    echo "Connected to database: scorecardtrakker_300000\n";
    echo "==========================================\n\n";
    
    // Get all tables in the database
    $stmt = $pdo->query("
        SELECT table_name 
        FROM information_schema.tables 
        WHERE table_schema = 'public' 
        AND table_type = 'BASE TABLE'
        ORDER BY table_name
    ");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($tables)) {
        echo "No tables found in the database.\n";
        exit(0);
    }
    
    echo "Found " . count($tables) . " tables:\n";
    foreach ($tables as $table) {
        echo "  - $table\n";
    }
    echo "\n";
    
    // Disable foreign key checks temporarily
    echo "Disabling foreign key constraints...\n";
    $pdo->exec("SET session_replication_role = 'replica';");
    
    // Delete data from all tables
    echo "\nDeleting data from all tables...\n";
    $deletedCount = 0;
    
    foreach ($tables as $table) {
        try {
            // Use TRUNCATE CASCADE to handle foreign key constraints
            $pdo->exec("TRUNCATE TABLE \"$table\" CASCADE;");
            $deletedCount++;
            echo "  ✓ Cleared table: $table\n";
        } catch (PDOException $e) {
            // If TRUNCATE fails (e.g., due to permissions), try DELETE
            try {
                $pdo->exec("DELETE FROM \"$table\";");
                $deletedCount++;
                echo "  ✓ Cleared table: $table (using DELETE)\n";
            } catch (PDOException $e2) {
                echo "  ✗ Failed to clear table: $table - " . $e2->getMessage() . "\n";
            }
        }
    }
    
    // Re-enable foreign key checks
    echo "\nRe-enabling foreign key constraints...\n";
    $pdo->exec("SET session_replication_role = 'origin';");
    
    echo "\n==========================================\n";
    echo "Successfully cleared $deletedCount out of " . count($tables) . " tables.\n";
    echo "All data has been deleted from scorecardtrakker_300000 database.\n";
    echo "Table structures remain intact.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

