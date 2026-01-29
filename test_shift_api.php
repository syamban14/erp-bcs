<?php
/**
 * Script Testing API Absensi Shift
 * 
 * Cara pakai:
 * 1. Pastikan server running: php artisan serve
 * 2. Jalankan: php test_shift_api.php
 */

$baseUrl = 'http://127.0.0.1:8000/api';

// ============================================
// STEP 1: LOGIN
// ============================================
echo "=== STEP 1: LOGIN ===\n";

$loginData = [
    'email' => 'admin@example.com',  // Ganti dengan email yang ada
    'password' => 'password'         // Ganti dengan password yang benar
];

$ch = curl_init($baseUrl . '/login');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n\n";

$loginResult = json_decode($response, true);

if (!isset($loginResult['token'])) {
    die("❌ Login gagal! Cek email/password.\n");
}

$token = $loginResult['token'];
echo "✅ Login berhasil!\n";
echo "Token: $token\n\n";

// ============================================
// STEP 2: CEK SHIFT HARI INI
// ============================================
echo "=== STEP 2: CEK SHIFT HARI INI ===\n";

$ch = curl_init($baseUrl . '/my-shift/today');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n\n";

$shiftData = json_decode($response, true);

if (isset($shiftData['data'])) {
    $shift = $shiftData['data'];
    if ($shift) {
        echo "✅ Shift hari ini:\n";
        echo "   Code: {$shift['shift_code']}\n";
        echo "   Name: {$shift['shift_name']}\n";
        echo "   Time: {$shift['time_in']} - {$shift['time_out']}\n";
        echo "   Off: " . ($shift['is_off'] ? 'Ya' : 'Tidak') . "\n\n";
        
        if ($shift['is_off']) {
            echo "⚠️ Hari ini libur! Clock in akan ditolak.\n\n";
        }
    } else {
        echo "⚠️ Tidak ada jadwal shift hari ini.\n\n";
    }
}

// ============================================
// STEP 3: CLOCK IN
// ============================================
echo "=== STEP 3: CLOCK IN ===\n";
echo "Apakah ingin lanjut clock in? (y/n): ";
$input = trim(fgets(STDIN));

if (strtolower($input) === 'y') {
    $clockInData = [
        'type' => 'in',
        'latitude' => '-6.200000',
        'longitude' => '106.816666'
    ];
    
    $ch = curl_init($baseUrl . '/presence');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($clockInData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Code: $httpCode\n";
    echo "Response: $response\n\n";
    
    $clockInResult = json_decode($response, true);
    
    if (isset($clockInResult['success']) && $clockInResult['success']) {
        echo "✅ Clock in berhasil!\n";
        
        if (isset($clockInResult['warning'])) {
            echo "⚠️ Warning: {$clockInResult['warning']}\n";
        }
        
        if (isset($clockInResult['data']['attendance_status'])) {
            $status = $clockInResult['data']['attendance_status'];
            echo "   Late: {$status['late_minutes']} menit\n";
        }
        echo "\n";
    } else {
        echo "❌ Clock in gagal!\n";
        if (isset($clockInResult['message'])) {
            echo "   Error: {$clockInResult['message']}\n";
        }
        echo "\n";
    }
} else {
    echo "⏭️ Skip clock in.\n\n";
}

// ============================================
// STEP 4: CLOCK OUT
// ============================================
echo "=== STEP 4: CLOCK OUT ===\n";
echo "Apakah ingin clock out? (y/n): ";
$input = trim(fgets(STDIN));

if (strtolower($input) === 'y') {
    $clockOutData = [
        'type' => 'out',
        'latitude' => '-6.200000',
        'longitude' => '106.816666'
    ];
    
    $ch = curl_init($baseUrl . '/presence');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($clockOutData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Code: $httpCode\n";
    echo "Response: $response\n\n";
    
    $clockOutResult = json_decode($response, true);
    
    if (isset($clockOutResult['success']) && $clockOutResult['success']) {
        echo "✅ Clock out berhasil!\n";
        
        if (isset($clockOutResult['info'])) {
            echo "ℹ️ Info: {$clockOutResult['info']}\n";
        }
        
        if (isset($clockOutResult['data']['attendance_status'])) {
            $status = $clockOutResult['data']['attendance_status'];
            echo "   Late: {$status['late_minutes']} menit\n";
            echo "   Overtime: {$status['overtime_minutes']} menit\n";
            echo "   Working Hours: {$status['working_hours']} jam\n";
        }
        echo "\n";
    } else {
        echo "❌ Clock out gagal!\n";
        if (isset($clockOutResult['message'])) {
            echo "   Error: {$clockOutResult['message']}\n";
        }
        echo "\n";
    }
} else {
    echo "⏭️ Skip clock out.\n\n";
}

// ============================================
// STEP 5: LIHAT HISTORY
// ============================================
echo "=== STEP 5: LIHAT HISTORY ===\n";

$ch = curl_init($baseUrl . '/presence');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";

$historyResult = json_decode($response, true);

if (isset($historyResult['data']) && count($historyResult['data']) > 0) {
    echo "✅ History absensi:\n\n";
    
    foreach (array_slice($historyResult['data'], 0, 3) as $record) {
        echo "Tanggal: {$record['date']}\n";
        echo "Clock In: {$record['clock_in']}\n";
        echo "Clock Out: " . ($record['clock_out'] ?? 'Belum') . "\n";
        
        if (isset($record['shift'])) {
            echo "Shift: {$record['shift']['code']} - {$record['shift']['name']}\n";
        }
        
        if (isset($record['attendance_status'])) {
            $status = $record['attendance_status'];
            echo "Late: {$status['late_minutes']} menit\n";
            echo "Overtime: {$status['overtime_minutes']} menit\n";
            if ($status['working_hours']) {
                echo "Working Hours: {$status['working_hours']} jam\n";
            }
        }
        
        echo "---\n";
    }
} else {
    echo "⚠️ Belum ada history absensi.\n";
}

echo "\n=== TESTING SELESAI ===\n";
