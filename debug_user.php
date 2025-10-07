<?php
use Cake\ORM\TableRegistry;
require_once 'vendor/autoload.php';
require_once 'config/bootstrap.php';

// Check what's in the Users table
$usersTable = TableRegistry::getTableLocator()->get('Users');
$user = $usersTable->find()->where(['username' => 'testuser'])->first();
if ($user) {
    echo "User found: " . json_encode($user->toArray()) . PHP_EOL;
} else {
    echo "User not found" . PHP_EOL;
}

// Also check all users
$allUsers = $usersTable->find()->toArray();
echo "All users: " . json_encode($allUsers) . PHP_EOL;
