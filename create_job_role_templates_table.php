<?php
// Create the job_role_templates table directly
use Cake\Datasource\ConnectionManager;
use Cake\Core\Configure;

require_once 'vendor/autoload.php';
require_once 'config/bootstrap.php';

// Ensure debug is enabled for test environment
Configure::write('debug', true);

try {
    echo "Creating job_role_templates table...\n";
    
    $connection = ConnectionManager::get('test');
    
    $sql = "
        CREATE TABLE IF NOT EXISTS job_role_templates (
            id SERIAL PRIMARY KEY,
            company_id INTEGER NOT NULL,
            name VARCHAR(150) NOT NULL,
            structure TEXT NOT NULL,
            deleted BOOLEAN NOT NULL DEFAULT FALSE,
            created_by VARCHAR(150) NOT NULL,
            created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ";
    
    $connection->execute($sql);
    echo "Table created successfully\n";
    
    // Verify table exists
    $result = $connection->execute("SELECT COUNT(*) FROM job_role_templates");
    $count = $result->fetchColumn(0);
    echo "Table now has {$count} records\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
