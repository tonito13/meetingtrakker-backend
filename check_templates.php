<?php
use Cake\Datasource\ConnectionManager;
require_once '/var/www/html/vendor/autoload.php';
require_once '/var/www/html/config/bootstrap.php';

$connection = ConnectionManager::get('test');
$result = $connection->execute('SELECT * FROM employee_templates WHERE company_id = ?', ['200001']);
$templates = $result->fetchAll('assoc');
echo 'Employee templates found: ' . count($templates) . PHP_EOL;
foreach ($templates as $template) {
    echo 'ID: ' . $template['id'] . ', Name: ' . $template['name'] . ', Structure: ' . substr($template['structure'], 0, 100) . '...' . PHP_EOL;
}
