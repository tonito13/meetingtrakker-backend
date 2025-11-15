<?php
/**
 * Test script for Company Mapping Service
 * 
 * This script tests if the CompanyMappingService can be instantiated and used
 */

require_once __DIR__ . '/vendor/autoload.php';

use Cake\Datasource\ConnectionManager;
use Cake\Core\Configure;

// Load CakePHP configuration
Configure::load('app', 'default', false);
Configure::load('app_local', 'default', false);

try {
    echo "Testing CompanyMappingService...\n\n";
    
    // Test 1: Check if we can get the default connection
    echo "1. Testing default connection...\n";
    $connection = ConnectionManager::get('default');
    echo "   ✓ Default connection successful\n\n";
    
    // Test 2: Check if tables exist
    echo "2. Checking if mapping tables exist...\n";
    $stmt = $connection->execute("
        SELECT table_name 
        FROM information_schema.tables 
        WHERE table_schema = 'public' 
        AND table_name IN ('companies', 'client_company_relationships', 'user_company_mappings')
    ");
    $tables = $stmt->fetchAll('assoc');
    $tableNames = array_column($tables, 'table_name');
    
    foreach (['companies', 'client_company_relationships', 'user_company_mappings'] as $table) {
        if (in_array($table, $tableNames)) {
            echo "   ✓ Table '{$table}' exists\n";
        } else {
            echo "   ✗ Table '{$table}' does NOT exist\n";
        }
    }
    echo "\n";
    
    // Test 3: Try to instantiate CompanyMappingService
    echo "3. Testing CompanyMappingService instantiation...\n";
    try {
        $mappingService = new \App\Service\CompanyMappingService();
        echo "   ✓ CompanyMappingService instantiated successfully\n\n";
        
        // Test 4: Try to get mapping for company 300000
        echo "4. Testing getOrgtrakkerCompanyIdFromScorecardtrakker(300000)...\n";
        try {
            $orgtrakkerCompanyId = $mappingService->getOrgtrakkerCompanyIdFromScorecardtrakker(300000);
            if ($orgtrakkerCompanyId !== null) {
                echo "   ✓ Mapping found: scorecardtrakker 300000 → orgtrakker {$orgtrakkerCompanyId}\n";
            } else {
                echo "   ⚠ No mapping found for company 300000\n";
                echo "   This is expected if no mapping has been created yet.\n";
            }
        } catch (\Exception $e) {
            echo "   ✗ Error getting mapping: " . $e->getMessage() . "\n";
            echo "   Trace: " . $e->getTraceAsString() . "\n";
        }
        echo "\n";
        
        // Test 5: Check if there are any mappings in the database
        echo "5. Checking existing mappings in database...\n";
        $stmt = $connection->execute("
            SELECT 
                ccr.id,
                ccr.company_id_from,
                ccr.company_id_to,
                ccr.relationship_type,
                ccr.status,
                c1.system_product_name as from_system,
                c2.system_product_name as to_system
            FROM client_company_relationships ccr
            LEFT JOIN companies c1 ON c1.company_id = ccr.company_id_from
            LEFT JOIN companies c2 ON c2.company_id = ccr.company_id_to
            WHERE ccr.deleted = false
            LIMIT 10
        ");
        $mappings = $stmt->fetchAll('assoc');
        
        if (empty($mappings)) {
            echo "   ⚠ No mappings found in database\n";
        } else {
            echo "   Found " . count($mappings) . " mapping(s):\n";
            foreach ($mappings as $mapping) {
                echo "     - ID {$mapping['id']}: {$mapping['company_id_from']} ({$mapping['from_system']}) → {$mapping['company_id_to']} ({$mapping['to_system']})\n";
            }
        }
        
    } catch (\Exception $e) {
        echo "   ✗ Error instantiating CompanyMappingService: " . $e->getMessage() . "\n";
        echo "   Trace: " . $e->getTraceAsString() . "\n";
    }
    
    echo "\n========================================\n";
    echo "Test completed\n";
    echo "========================================\n";
    
} catch (\Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

