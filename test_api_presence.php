<?php

require __DIR__ . '/vendor/autoload.php';

function callApi($url, $method = 'GET', $data = [], $token = null) {
    echo "Calling $method $url...\n";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $headers = [
        'Accept: application/json',
        'Content-Type: application/json'
    ];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $httpCode, 'body' => json_decode($response, true)];
}

// 1. Login
$loginResp = callApi('http://localhost:8000/api/login', 'POST', [
    'email' => 'budi@presensi.com',
    'password' => 'password123',
    'device_token' => 'device-123'
]);

if ($loginResp['code'] !== 200) {
    die("Login failed: " . json_encode($loginResp) . "\n");
}

$token = $loginResp['body']['token'];
echo "Token received: " . substr($token, 0, 10) . "...\n";

// 2. Clock In
$clockInResp = callApi('http://localhost:8000/api/presence', 'POST', [
    'type' => 'in', // Assuming controller logic handles 'in' type logic or we just post data
    // Controller validation usually: latitude, longitude, photo?
    'latitude' => '-6.2088',
    'longitude' => '106.8456',
    // 'photo' => 'base64string...' ? Let's check controller first.
    // If controller expects file upload via multipart/form-data, json might fail.
], $token);

echo "Clock In Response (" . $clockInResp['code'] . "):\n";
print_r($clockInResp['body']);

