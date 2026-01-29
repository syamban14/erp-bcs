<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $columns = DB::connection('pgsql_master')->select("
        SELECT column_name, data_type 
        FROM information_schema.columns 
        WHERE table_name = 'm_karyawan'
    ");
    
    echo "Columns in m_karyawan (from information_schema):\n";
    foreach ($columns as $col) {
        echo "- " . $col->column_name . " (" . $col->data_type . ")\n";
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
