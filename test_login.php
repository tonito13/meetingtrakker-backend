<?php
use Cake\ORM\TableRegistry;
require_once 'vendor/autoload.php';
require_once 'config/bootstrap.php';

// Test the login process
$usersTable = TableRegistry::getTableLocator()->get('Users');
$user = $usersTable->find()->where(['username' => 'test'])->first();

if ($user) {
    echo "User found: " . $user->username . PHP_EOL;
    echo "Company ID: " . $user->company_id . PHP_EOL;
    echo "Password hash: " . $user->password . PHP_EOL;
    
    // Test password verification
    $testPassword = '12345';
    $isValid = password_verify($testPassword, $user->password);
    echo "Password '12345' is valid: " . ($isValid ? 'YES' : 'NO') . PHP_EOL;
    
    // Generate token like the controller does
    $jwtKey = file_get_contents(CONFIG . '/jwt.key');
    $issuedAt = time();
    
    $payload = [
        'sub' => $user->id,
        'company_id' => $user->company_id,
        'exp' => time() + 28800,
        'iat' => $issuedAt,
    ];
    
    $token = \Firebase\JWT\JWT::encode($payload, $jwtKey, 'RS256');
    echo "Generated token: " . $token . PHP_EOL;
    
    // Decode the token to verify
    $publicKey = file_get_contents(CONFIG . '/jwt.pem');
    $decoded = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($publicKey, 'RS256'));
    echo "Decoded token: " . json_encode($decoded) . PHP_EOL;
} else {
    echo "User not found" . PHP_EOL;
}
