<?php

use App\Models\User;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $user = User::firstOrCreate(
        ['email' => 'superadmin@admin.com'],
        ['name' => 'Super Admin', 'password' => bcrypt('password')]
    );
    echo "User found/created: " . $user->email . "\n";
    echo "Password: password\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
