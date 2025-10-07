<?php
use Cake\Datasource\ConnectionManager;
require_once '/var/www/html/vendor/autoload.php';
require_once '/var/www/html/config/bootstrap.php';

$connection = ConnectionManager::get('test');
$result = $connection->execute('SELECT column_name, data_type, column_default FROM information_schema.columns WHERE table_name = ?', ['employee_templates']);
$columns = $result->fetchAll('assoc');
foreach ($columns as $column) {
    echo $column['column_name'] . ' - ' . $column['data_type'] . ' - ' . $column['column_default'] . PHP_EOL;
}
