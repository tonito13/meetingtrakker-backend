<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link      https://cakephp.org CakePHP(tm) Project
 * @since     3.0.0
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 */

use Cake\Chronos\Chronos;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\ConnectionHelper;

/**
 * Test runner bootstrap.
 *
 * Add additional configuration/setup your application needs when running
 * unit tests in this file.
 */
require dirname(__DIR__) . '/vendor/autoload.php';

require dirname(__DIR__) . '/config/bootstrap.php';

if (empty($_SERVER['HTTP_HOST']) && !Configure::read('App.fullBaseUrl')) {
    Configure::write('App.fullBaseUrl', 'http://localhost');
}

// DebugKit skips settings these connection config if PHP SAPI is CLI / PHPDBG.
// But since PagesControllerTest is run with debug enabled and DebugKit is loaded
// in application, without setting up these config DebugKit errors out.
ConnectionManager::setConfig('test_debug_kit', [
    'className' => 'Cake\Database\Connection',
    'driver' => 'Cake\Database\Driver\Sqlite',
    'database' => TMP . 'debug_kit.sqlite',
    'encoding' => 'utf8',
    'cacheMetadata' => true,
    'quoteIdentifiers' => false,
]);

ConnectionManager::alias('test_debug_kit', 'debug_kit');

// Fixate now to avoid one-second-leap-issues
Chronos::setTestNow(Chronos::now());

// Fixate sessionid early on, as php7.2+
// does not allow the sessionid to be set after stdout
// has been written to.
session_id('cli');

// Connection aliasing needs to happen before migrations are run.
// Otherwise, table objects inside migrations would use the default datasource
ConnectionHelper::addTestAliases();

// Create test alias for company-specific test database (fixtures require names starting with 'test')
// This allows fixtures to use 'test_client_200001' which points to 'client_200001_test'
ConnectionManager::alias('client_200001_test', 'test_client_200001');

// Create test alias for orgtrakker test database (fixtures require names starting with 'test')
// This allows fixtures to use 'test_orgtrakker_100000' which points to 'orgtrakker_100000_test'
// However, since fixtures use 'orgtrakker_100000_test' directly, we'll create an alias for consistency
ConnectionManager::alias('orgtrakker_100000_test', 'test_orgtrakker_100000');

// Test database schema is already created from SQL dumps (workmatica.sql and scorecardtrakker_300000.sql)
// We just need to truncate all tables to ensure a clean state for each test run
$connection = ConnectionManager::get('test');

// Dynamically truncate all tables in the test database
// This ensures clean state without hardcoding table names
$tables = $connection->execute("
    SELECT tablename 
    FROM pg_tables 
    WHERE schemaname = 'public' 
    ORDER BY tablename
")->fetchAll('assoc');

// Truncate all tables with CASCADE to handle foreign key constraints
// We do this in reverse order to minimize foreign key constraint issues
// (though CASCADE should handle it, this is safer)
foreach (array_reverse($tables) as $table) {
    $tableName = $table['tablename'];
    try {
        // Use quoteIdentifier to safely quote table name
        $quotedTableName = $connection->getDriver()->quoteIdentifier($tableName);
        $connection->execute("TRUNCATE TABLE {$quotedTableName} RESTART IDENTITY CASCADE");
    } catch (\Exception $e) {
        // Some tables might not exist or might have issues, continue with others
        // This is expected for tables that might be created conditionally
        continue;
    }
}

// (new Migrator())->run();
