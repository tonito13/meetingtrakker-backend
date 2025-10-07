<?php
use Cake\Datasource\ConnectionManager;
require_once '/var/www/html/vendor/autoload.php';
require_once '/var/www/html/config/bootstrap.php';

$connection = ConnectionManager::get('test');
echo 'Checking all template tables:' . PHP_EOL;
$tables = ['employee_templates', 'scorecard_templates', 'job_role_templates', 'level_templates'];
foreach ($tables as $table) {
    try {
        $result = $connection->execute('SELECT COUNT(*) as count FROM ' . $table . ' WHERE company_id = ?', ['200001']);
        $count = $result->fetch('assoc')['count'];
        echo $table . ': ' . $count . ' templates' . PHP_EOL;
    } catch (Exception $e) {
        echo $table . ': Error - ' . $e->getMessage() . PHP_EOL;
    }
}
