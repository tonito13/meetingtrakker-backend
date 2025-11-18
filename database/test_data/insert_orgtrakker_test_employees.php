<?php
/**
 * Script to insert test employees into orgtrakker_100000 database
 * Run this from the backend directory: php database/test_data/insert_orgtrakker_test_employees.php
 */

require __DIR__ . '/../../vendor/autoload.php';

use Cake\Datasource\ConnectionManager;
use Cake\Core\Configure;

// Load CakePHP configuration
require __DIR__ . '/../../config/bootstrap.php';

try {
    // Get the orgtrakker connection
    $connection = ConnectionManager::get('orgtrakker_100000');
    
    // Test employees data
    $testEmployees = [
        [
            'company_id' => 100000,
            'employee_unique_id' => 'emp-100001',
            'employee_id' => 'EMP001',
            'username' => 'john.doe',
            'template_id' => 1,
            'answers' => '{}',
            'deleted' => false,
            'created_by' => 'system',
        ],
        [
            'company_id' => 100000,
            'employee_unique_id' => 'emp-100002',
            'employee_id' => 'EMP002',
            'username' => 'jane.smith',
            'template_id' => 1,
            'answers' => '{}',
            'deleted' => false,
            'created_by' => 'system',
        ],
        [
            'company_id' => 100000,
            'employee_unique_id' => 'emp-100003',
            'employee_id' => 'EMP003',
            'username' => 'bob.johnson',
            'template_id' => 1,
            'answers' => '{}',
            'deleted' => false,
            'created_by' => 'system',
        ],
        [
            'company_id' => 100000,
            'employee_unique_id' => 'emp-100004',
            'employee_id' => 'EMP004',
            'username' => 'alice.williams',
            'template_id' => 1,
            'answers' => '{}',
            'deleted' => false,
            'created_by' => 'system',
        ],
        [
            'company_id' => 100000,
            'employee_unique_id' => 'emp-100005',
            'employee_id' => 'EMP005',
            'username' => 'charlie.brown',
            'template_id' => 1,
            'answers' => '{}',
            'deleted' => false,
            'created_by' => 'system',
        ],
        [
            'company_id' => 100000,
            'employee_unique_id' => 'emp-100006',
            'employee_id' => 'EMP006',
            'username' => 'diana.prince',
            'template_id' => 1,
            'answers' => '{}',
            'deleted' => false,
            'created_by' => 'system',
        ],
        [
            'company_id' => 100000,
            'employee_unique_id' => 'emp-100007',
            'employee_id' => 'EMP007',
            'username' => 'frank.miller',
            'template_id' => 1,
            'answers' => '{}',
            'deleted' => false,
            'created_by' => 'system',
        ],
        [
            'company_id' => 100000,
            'employee_unique_id' => 'emp-100008',
            'employee_id' => 'EMP008',
            'username' => 'grace.hopper',
            'template_id' => 1,
            'answers' => '{}',
            'deleted' => false,
            'created_by' => 'system',
        ],
        [
            'company_id' => 100000,
            'employee_unique_id' => 'emp-100009',
            'employee_id' => 'EMP009',
            'username' => 'henry.ford',
            'template_id' => 1,
            'answers' => '{}',
            'deleted' => false,
            'created_by' => 'system',
        ],
        [
            'company_id' => 100000,
            'employee_unique_id' => 'emp-100010',
            'employee_id' => 'EMP010',
            'username' => 'ivy.league',
            'template_id' => 1,
            'answers' => '{}',
            'deleted' => false,
            'created_by' => 'system',
        ],
    ];
    
    $inserted = 0;
    $skipped = 0;
    
    foreach ($testEmployees as $employee) {
        // Check if employee already exists
        $stmt = $connection->execute(
            'SELECT id FROM employee_template_answers WHERE employee_unique_id = :employee_unique_id AND deleted = false',
            ['employee_unique_id' => $employee['employee_unique_id']]
        );
        
        if ($stmt->fetch('assoc')) {
            echo "Employee {$employee['username']} already exists, skipping...\n";
            $skipped++;
            continue;
        }
        
        // Insert the employee
        $connection->execute(
            'INSERT INTO employee_template_answers (company_id, employee_unique_id, employee_id, username, template_id, answers, deleted, created_by, created, modified)
             VALUES (:company_id, :employee_unique_id, :employee_id, :username, :template_id, :answers::jsonb, :deleted, :created_by, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
            $employee
        );
        
        echo "Inserted employee: {$employee['username']} ({$employee['employee_unique_id']})\n";
        $inserted++;
    }
    
    echo "\nDone! Inserted {$inserted} employees, skipped {$skipped} existing employees.\n";
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

