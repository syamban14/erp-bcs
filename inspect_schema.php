<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $columns = Schema::connection('pgsql_master')->getColumnListing('m_karyawan');
    echo "Columns in m_karyawan:\n";
    print_r($columns);
    
    // Also check m_presensi just in case
    $presensiColumns = Schema::connection('pgsql_master')->getColumnListing('m_presensi');
    echo "\nColumns in m_presensi:\n";
    print_r($presensiColumns);
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
