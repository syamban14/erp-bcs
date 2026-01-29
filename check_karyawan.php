<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== M_KARYAWAN TABLE COLUMNS ===\n";
$columns = \Illuminate\Support\Facades\Schema::connection('pgsql_master')->getColumnListing('m_karyawan');
print_r($columns);

echo "\n=== SAMPLE DATA ===\n";
$karyawan = \App\Models\MKaryawan::first();
if ($karyawan) {
    print_r($karyawan->toArray());
}
