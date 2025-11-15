<?php
/**
 * Check for missing indexes on companies table
 */

$host = 'localhost';
$port = 5433;
$database = 'workmatica';
$username = 'workmatica_user';
$password = 'securepassword';

try {
    $dsn = "pgsql:host={$host};port={$port};dbname={$database}";
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Checking for missing indexes on companies table...\n\n";

    // Expected indexes
    $expectedIndexes = [
        'idx_companies_company_id',
        'idx_companies_system_product_name',
        'idx_companies_code',
        'idx_companies_deleted',
        'idx_companies_company_id_system',
        'idx_companies_status',
        'uq_companies_company_id_system'
    ];

    // Get existing indexes
    $stmt = $pdo->query("
        SELECT indexname
        FROM pg_indexes
        WHERE schemaname = 'public' 
        AND tablename = 'companies'
    ");
    $existingIndexes = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "Existing indexes:\n";
    foreach ($existingIndexes as $idx) {
        echo "  - {$idx}\n";
    }

    echo "\nExpected indexes:\n";
    foreach ($expectedIndexes as $idx) {
        $exists = in_array($idx, $existingIndexes);
        $status = $exists ? '✓' : '✗';
        echo "  {$status} {$idx}\n";
    }

    // Check for missing indexes
    $missing = array_diff($expectedIndexes, $existingIndexes);
    if (!empty($missing)) {
        echo "\nMissing indexes:\n";
        foreach ($missing as $idx) {
            echo "  - {$idx}\n";
        }
    } else {
        echo "\n✓ All expected indexes exist!\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

