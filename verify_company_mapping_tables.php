<?php
/**
 * Verification script for Company Mapping tables
 * 
 * This script checks if the company mapping tables have been created correctly
 * in the workmatica database.
 */

// Database connection details
$host = 'localhost';
$port = 5433;
$database = 'workmatica';
$username = 'workmatica_user';
$password = 'securepassword';

try {
    // Create connection
    $dsn = "pgsql:host={$host};port={$port};dbname={$database}";
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "========================================\n";
    echo "Company Mapping Tables Verification\n";
    echo "========================================\n\n";
    echo "Database: {$database}\n";
    echo "Host: {$host}:{$port}\n\n";

    // Tables to check
    $tables = [
        'companies',
        'client_company_relationships',
        'user_company_mappings'
    ];

    $allTablesExist = true;

    foreach ($tables as $tableName) {
        echo "----------------------------------------\n";
        echo "Checking table: {$tableName}\n";
        echo "----------------------------------------\n";

        // Check if table exists
        $stmt = $pdo->prepare("
            SELECT EXISTS (
                SELECT FROM information_schema.tables 
                WHERE table_schema = 'public' 
                AND table_name = :table_name
            )
        ");
        $stmt->execute(['table_name' => $tableName]);
        $exists = $stmt->fetchColumn();

        if ($exists) {
            echo "✓ Table exists\n\n";

            // Get column information
            $stmt = $pdo->prepare("
                SELECT 
                    column_name,
                    data_type,
                    character_maximum_length,
                    is_nullable,
                    column_default
                FROM information_schema.columns
                WHERE table_schema = 'public' 
                AND table_name = :table_name
                ORDER BY ordinal_position
            ");
            $stmt->execute(['table_name' => $tableName]);
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo "Columns:\n";
            foreach ($columns as $column) {
                $type = $column['data_type'];
                if ($column['character_maximum_length']) {
                    $type .= "({$column['character_maximum_length']})";
                }
                $nullable = $column['is_nullable'] === 'YES' ? 'NULL' : 'NOT NULL';
                $default = $column['column_default'] ? " DEFAULT {$column['column_default']}" : '';
                echo "  - {$column['column_name']}: {$type} {$nullable}{$default}\n";
            }

            // Get indexes
            $stmt = $pdo->prepare("
                SELECT 
                    indexname,
                    indexdef
                FROM pg_indexes
                WHERE schemaname = 'public' 
                AND tablename = :table_name
                ORDER BY indexname
            ");
            $stmt->execute(['table_name' => $tableName]);
            $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo "\nIndexes:\n";
            foreach ($indexes as $index) {
                echo "  - {$index['indexname']}\n";
            }

            // Get constraints
            $stmt = $pdo->prepare("
                SELECT 
                    conname AS constraint_name,
                    contype AS constraint_type,
                    pg_get_constraintdef(oid) AS constraint_definition
                FROM pg_constraint
                WHERE conrelid = (
                    SELECT oid FROM pg_class WHERE relname = :table_name
                )
                ORDER BY conname
            ");
            $stmt->execute(['table_name' => $tableName]);
            $constraints = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($constraints)) {
                echo "\nConstraints:\n";
                foreach ($constraints as $constraint) {
                    $type = '';
                    switch ($constraint['constraint_type']) {
                        case 'p': $type = 'PRIMARY KEY'; break;
                        case 'f': $type = 'FOREIGN KEY'; break;
                        case 'u': $type = 'UNIQUE'; break;
                        case 'c': $type = 'CHECK'; break;
                        default: $type = 'OTHER'; break;
                    }
                    echo "  - {$constraint['constraint_name']} ({$type}): {$constraint['constraint_definition']}\n";
                }
            }

            // Get row count
            $stmt = $pdo->query("SELECT COUNT(*) FROM {$tableName}");
            $rowCount = $stmt->fetchColumn();
            echo "\nRow count: {$rowCount}\n";

        } else {
            echo "✗ Table does NOT exist\n";
            $allTablesExist = false;
        }

        echo "\n";
    }

    echo "========================================\n";
    if ($allTablesExist) {
        echo "✓ All tables exist!\n";
    } else {
        echo "✗ Some tables are missing!\n";
    }
    echo "========================================\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

