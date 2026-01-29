<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$shift = App\Models\ShiftCode::first();

if ($shift) {
    echo "Code: " . $shift->code . "\n";
    echo "Name: " . $shift->name . "\n";
    echo "Time In: " . $shift->time_in . "\n";
    echo "Time In Type: " . gettype($shift->time_in) . "\n";
    echo "Time Out: " . $shift->time_out . "\n";
    echo "Time Out Type: " . gettype($shift->time_out) . "\n";
    
    if ($shift->time_in instanceof \Carbon\Carbon) {
        echo "Time In is Carbon\n";
        echo "Formatted: " . $shift->time_in->format('H:i') . "\n";
    }
} else {
    echo "No shift codes found\n";
}
