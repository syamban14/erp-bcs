<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $columns = Schema::connection('pgsql_master')->getColumnListing('m_karyawan');
    echo "Total Columns in m_karyawan: " . count($columns) . "\n";
    echo "First 20 Columns:\n";
    print_r(array_slice($columns, 0, 20));
    
    echo "\nFirst row data sample (m_karyawan):\n";
    $firstRow = DB::connection('pgsql_master')->table('m_karyawan')->first();
    print_r($firstRow);

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
