<?php
// Test if the JobRoleTemplates table exists
use Cake\ORM\TableRegistry;
use Cake\Datasource\ConnectionManager;
use Cake\Core\Configure;

require_once 'vendor/autoload.php';
require_once 'config/bootstrap.php';

// Ensure debug is enabled for test environment
Configure::write('debug', true);

try {
    echo "Testing JobRoleTemplates table access...\n";
    
    $connection = ConnectionManager::get('test');
    $table = TableRegistry::getTableLocator()->get('JobRoleTemplates', ['connection' => $connection]);
    echo "Table loaded successfully\n";
    
    $count = $table->find()->count();
    echo "Total records: {$count}\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
