<?php
// Test the actual login endpoint
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/api/users/login');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'username' => 'test',
    'password' => '12345'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: " . $httpCode . PHP_EOL;
echo "Response: " . $response . PHP_EOL;
