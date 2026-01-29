<?php

use App\Models\MKaryawan;
use App\Models\MPresensi;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    // 1. Find or Create Employee (m_karyawan)
    // Using existing employee to avoid ID sequence issues
    $karyawan = MKaryawan::first();
    
    if (!$karyawan) {
        throw new Exception("No employees found in m_karyawan. Cannot seed user.");
    }
    
    echo "Using Existing Employee: " . $karyawan->nama_karyawan . " (ID: " . $karyawan->id . ")\n";

    // 2. Create Mobile User (m_presensi)
    // Now m_presensi has karyawan_id linked
    $mPresensi = MPresensi::updateOrCreate(
        ['email' => 'budi@presensi.com'],
        [
            'karyawan_id' => $karyawan->id,
            'name' => 'Budi Mobile', // m_presensi has 'name' col based on inspect output
            'password' => Hash::make('password123'),
            'is_active' => true,
            'device_token' => 'dummy-token'
        ]
    );
    
    echo "Mobile User Updated/Created: " . $mPresensi->email . "\n";
    echo "Password: password123\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
