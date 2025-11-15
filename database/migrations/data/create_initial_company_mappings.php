<?php
/**
 * Initial Company Mappings Data Migration Script
 * 
 * This script creates initial company mappings for existing companies.
 * Run this script after running the database migrations to set up
 * the initial mappings between orgtrakker and scorecardtrakker companies.
 * 
 * Usage:
 * php database/migrations/data/create_initial_company_mappings.php
 * 
 * Or execute the SQL directly in pgAdmin or psql.
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

use Cake\Datasource\ConnectionManager;
use Cake\Core\Configure;

// Load CakePHP configuration
Configure::load('app', 'default', false);
Configure::load('app_local', 'default', false);

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

    echo "Connected to database successfully.\n\n";

    // Example: Create mapping for "Test Company"
    // Orgtrakker company_id: 100000
    // ScorecardTrakker company_id: 300000
    
    echo "Creating initial company mappings...\n\n";

    // First, ensure companies exist in the companies table
    echo "1. Checking/creating company records...\n";
    
    // Check if companies exist, if not create them
    $checkCompany = $pdo->prepare("
        SELECT id FROM companies 
        WHERE company_id = :company_id AND system_product_name = :system_product_name AND deleted = false
    ");

    // Orgtrakker company (100000)
    $checkCompany->execute(['company_id' => 100000, 'system_product_name' => 'orgtrakker']);
    $orgtrakkerCompany = $checkCompany->fetch(PDO::FETCH_ASSOC);
    
    if (!$orgtrakkerCompany) {
        echo "   Creating orgtrakker company record (ID: 100000)...\n";
        $insertCompany = $pdo->prepare("
            INSERT INTO companies (
                company_id, company_type, company_status, data_privacy_setup_type_id,
                code, email, maximum_users, name, system_product_name, deleted, created, modified
            ) VALUES (
                100000, 'principal', 'active', 1,
                'TC01', 'test@test.com', 100, 'Test Company Orgtrakker', 'orgtrakker', false, NOW(), NOW()
            )
        ");
        $insertCompany->execute();
        echo "   ✓ Created orgtrakker company record\n";
    } else {
        echo "   ✓ Orgtrakker company record already exists\n";
    }

    // ScorecardTrakker company (300000)
    $checkCompany->execute(['company_id' => 300000, 'system_product_name' => 'scorecardtrakker']);
    $scorecardtrakkerCompany = $checkCompany->fetch(PDO::FETCH_ASSOC);
    
    if (!$scorecardtrakkerCompany) {
        echo "   Creating scorecardtrakker company record (ID: 300000)...\n";
        $insertCompany = $pdo->prepare("
            INSERT INTO companies (
                company_id, company_type, company_status, data_privacy_setup_type_id,
                code, email, maximum_users, name, system_product_name, deleted, created, modified
            ) VALUES (
                300000, 'principal', 'active', 1,
                'TC02', 'test@test.com', 100, 'Test Company ScorecardTrakker', 'scorecardtrakker', false, NOW(), NOW()
            )
        ");
        $insertCompany->execute();
        echo "   ✓ Created scorecardtrakker company record\n";
    } else {
        echo "   ✓ ScorecardTrakker company record already exists\n";
    }

    echo "\n2. Creating company mapping...\n";
    
    // Check if mapping already exists
    $checkMapping = $pdo->prepare("
        SELECT id FROM client_company_relationships 
        WHERE company_id_from = :company_id_from 
        AND company_id_to = :company_id_to 
        AND relationship_type = 'affiliate'
        AND deleted = false
    ");
    
    $checkMapping->execute([
        'company_id_from' => 100000,
        'company_id_to' => 300000
    ]);
    $existingMapping = $checkMapping->fetch(PDO::FETCH_ASSOC);

    if (!$existingMapping) {
        // Create mapping: orgtrakker 100000 → scorecardtrakker 300000
        $insertMapping = $pdo->prepare("
            INSERT INTO client_company_relationships (
                company_id_from, company_id_to, relationship_type, status, is_primary,
                start_date, end_date, notes, metadata, deleted, created_at, updated_at
            ) VALUES (
                100000, 300000, 'affiliate', 'active', true,
                CURRENT_DATE, NULL, 
                'Initial mapping: Test Company - Orgtrakker (100000) to ScorecardTrakker (300000)',
                '{\"system_from\": \"orgtrakker\", \"system_to\": \"scorecardtrakker\"}'::jsonb,
                false, NOW(), NOW()
            )
        ");
        $insertMapping->execute();
        echo "   ✓ Created mapping: orgtrakker 100000 → scorecardtrakker 300000\n";
    } else {
        echo "   ✓ Mapping already exists (ID: {$existingMapping['id']})\n";
    }

    // Also create reverse mapping for bidirectional support
    $checkReverseMapping = $pdo->prepare("
        SELECT id FROM client_company_relationships 
        WHERE company_id_from = :company_id_from 
        AND company_id_to = :company_id_to 
        AND relationship_type = 'affiliate'
        AND deleted = false
    ");
    
    $checkReverseMapping->execute([
        'company_id_from' => 300000,
        'company_id_to' => 100000
    ]);
    $existingReverseMapping = $checkReverseMapping->fetch(PDO::FETCH_ASSOC);

    if (!$existingReverseMapping) {
        $insertReverseMapping = $pdo->prepare("
            INSERT INTO client_company_relationships (
                company_id_from, company_id_to, relationship_type, status, is_primary,
                start_date, end_date, notes, metadata, deleted, created_at, updated_at
            ) VALUES (
                300000, 100000, 'affiliate', 'active', false,
                CURRENT_DATE, NULL, 
                'Reverse mapping: Test Company - ScorecardTrakker (300000) to Orgtrakker (100000)',
                '{\"system_from\": \"scorecardtrakker\", \"system_to\": \"orgtrakker\"}'::jsonb,
                false, NOW(), NOW()
            )
        ");
        $insertReverseMapping->execute();
        echo "   ✓ Created reverse mapping: scorecardtrakker 300000 → orgtrakker 100000\n";
    } else {
        echo "   ✓ Reverse mapping already exists (ID: {$existingReverseMapping['id']})\n";
    }

    echo "\n✓ Initial company mappings created successfully!\n\n";
    echo "Summary:\n";
    echo "  - Orgtrakker company (100000) ↔ ScorecardTrakker company (300000)\n";
    echo "  - Relationship type: affiliate\n";
    echo "  - Status: active\n\n";
    echo "You can now use the import functions with company ID mapping.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

