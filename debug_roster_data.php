<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\ShiftSchedule;
use App\Models\ShiftCode;
use Carbon\Carbon;

// Test data loading
$selectedMonth = 12;
$selectedYear = 2025;

// Generate dates
$start = Carbon::create($selectedYear, $selectedMonth, 16);
$endOfMonth = $start->copy()->endOfMonth();

$dates = [];
for ($date = $start->copy(); $date->lte($endOfMonth); $date->addDay()) {
    $dates[] = $date->copy();
}

$nextMonth = $start->copy()->addMonth()->startOfMonth();
for ($i = 1; $i <= 15; $i++) {
    $dates[] = $nextMonth->copy()->day($i);
}

echo "Total dates: " . count($dates) . "\n";
echo "First date: " . $dates[0]->format('Y-m-d') . "\n";
echo "Last date: " . $dates[count($dates)-1]->format('Y-m-d') . "\n\n";

// Get date strings
$dateStrings = array_map(fn($d) => $d->format('Y-m-d'), $dates);

// Load schedules
$schedules = ShiftSchedule::whereIn('date', $dateStrings)->get();

echo "Total schedules found: " . $schedules->count() . "\n\n";

if ($schedules->count() > 0) {
    $firstSchedule = $schedules->first();
    echo "First schedule:\n";
    echo "  User ID: " . $firstSchedule->user_id . "\n";
    echo "  Date: " . $firstSchedule->date . "\n";
    echo "  Shift Code: " . $firstSchedule->shift_code . "\n";
    
    $shiftCode = ShiftCode::where('code', $firstSchedule->shift_code)->first();
    if ($shiftCode) {
        echo "  Shift Name: " . $shiftCode->name . "\n";
        echo "  Time In: " . $shiftCode->time_in . "\n";
        echo "  Time Out: " . $shiftCode->time_out . "\n";
        echo "  Is Off: " . ($shiftCode->is_off ? 'Yes' : 'No') . "\n";
    } else {
        echo "  Shift Code not found!\n";
    }
} else {
    echo "No schedules found in database for this period!\n";
    echo "Please check:\n";
    echo "1. Have you imported roster data?\n";
    echo "2. Are there schedules in shift_schedules table?\n";
}
