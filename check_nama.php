<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $cols = DB::connection('pgsql_master')->select("SELECT column_name FROM information_schema.columns WHERE table_name = 'm_karyawan' AND (column_name ILIKE 'k%' OR column_name ILIKE 'i%')");
    foreach ($cols as $c) {
        echo "[" . $c->column_name . "]\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
