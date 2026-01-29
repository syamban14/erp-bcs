<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    echo "--- Default Connection (presensi_db) ---\n";
    $tokens = DB::connection('pgsql')->table('personal_access_tokens')->orderBy('created_at', 'desc')->limit(3)->get();
    foreach ($tokens as $t) {
        echo "ID: " . $t->id . " | Type: " . $t->tokenable_type . " | ID: " . $t->tokenable_id . " | Name: " . $t->name . "\n";
    }

    echo "\n--- Master Connection (master_db) ---\n";
    try {
        $tokensMaster = DB::connection('pgsql_master')->table('personal_access_tokens')->orderBy('created_at', 'desc')->limit(3)->get();
        foreach ($tokensMaster as $t) {
            echo "ID: " . $t->id . " | Type: " . $t->tokenable_type . " | ID: " . $t->tokenable_id . " | Name: " . $t->name . "\n";
        }
    } catch (\Exception $e) {
        echo "Table not found or error in master: " . $e->getMessage() . "\n";
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
