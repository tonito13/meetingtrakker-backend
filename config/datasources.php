<?php
// config/app_local.php or similar custom loader
namespace Config;

use Cake\Database\Connection;
use Cake\Database\Driver\Postgres;

/**
 * Reusable factory for datasource config.
 */
function setupDataSource($host, $username, $password, $database) {
    return [
        'className' => Connection::class,
        'driver' => Postgres::class,
        'persistent' => false,
        'host' => $host,
        'port' => 5432,
        'username' => $username,
        'password' => $password,
        'database' => $database,
        'encoding' => 'utf8',
        'timezone' => 'UTC',
        'cacheMetadata' => true,
        'quoteIdentifiers' => false,
        'log' => false,
    ];
}

$dataSources = [];

$dataSources['default'] = setupDataSource('scorecardtrakker_postgres_database', 'scorecardtrakker_user', 'securepassword', 'scorecardtrakker');
$dataSources['test'] = setupDataSource('scorecardtrakker_postgres_database', 'scorecardtrakker_user', 'securepassword', 'scorecardtrakker');
$dataSources['client_200001'] = setupDataSource('scorecardtrakker_postgres_database', 'scorecardtrakker_user', 'securepassword', '200001');

// Return only the datasources array, not nested
return $dataSources;
