<?php
require_once 'vendor/autoload.php';

use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;

// Load configuration
Configure::load('app', 'default', false);

try {
    // Test the test database connection
    $connection = ConnectionManager::get('test');
    echo "Test database connection successful\n";
    echo "Database: " . $connection->config()['database'] . "\n";
    
    // Check if the table exists
    $schema = $connection->getSchemaCollection();
    $tables = $schema->listTables();
    echo "Tables in test database: " . implode(', ', $tables) . "\n";
    
    // Test the client_200001_test connection
    $clientConnection = ConnectionManager::get('client_200001_test');
    echo "Client test database connection successful\n";
    echo "Client Database: " . $clientConnection->config()['database'] . "\n";
    
    $clientSchema = $clientConnection->getSchemaCollection();
    $clientTables = $clientSchema->listTables();
    echo "Tables in client test database: " . implode(', ', $clientTables) . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
