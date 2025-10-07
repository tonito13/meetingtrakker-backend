<?php
// Test if the JobRoleTemplateAnswers table exists and has data
use Cake\ORM\TableRegistry;
use Cake\Datasource\ConnectionManager;
use Cake\Core\Configure;

require_once 'vendor/autoload.php';
require_once 'config/bootstrap.php';

// Ensure debug is enabled for test environment
Configure::write('debug', true);

try {
    echo "Testing JobRoleTemplateAnswers table access...\n";
    
    $connection = ConnectionManager::get('test');
    $table = TableRegistry::getTableLocator()->get('JobRoleTemplateAnswers', ['connection' => $connection]);
    echo "Table loaded successfully\n";
    
    $count = $table->find()->count();
    echo "Total records: {$count}\n";
    
    if ($count > 0) {
        $firstRecord = $table->find()->first();
        echo "First record ID: " . $firstRecord->id . "\n";
        echo "First record job_role_unique_id: " . $firstRecord->job_role_unique_id . "\n";
        echo "First record company_id: " . $firstRecord->company_id . "\n";
        echo "First record deleted: " . ($firstRecord->deleted ? 'true' : 'false') . "\n";
    }
    
    // Test specific record lookup
    $specificRecord = $table->find()
        ->where([
            'company_id' => 200001,
            'deleted' => 0,
            'job_role_unique_id' => 'jr-20240101-ABCD1234'
        ])
        ->first();
        
    if ($specificRecord) {
        echo "Found specific record: " . $specificRecord->job_role_unique_id . "\n";
    } else {
        echo "Specific record NOT found\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
