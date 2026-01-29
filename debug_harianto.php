<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== DEBUG SCHEDULE FOR HARIANTO ===\n\n";

// Find user harianto
$user = App\Models\MPresensi::where('name', 'LIKE', '%harianto%')->first();

if (!$user) {
    echo "ERROR: User harianto not found\n";
    exit;
}

echo "User ID: {$user->id}\n";
echo "User Name: {$user->name}\n\n";

// Check if has any shift schedule
$hasAnyShiftSchedule = App\Models\ShiftSchedule::where('user_id', $user->id)->exists();
echo "Has Any Shift Schedule: " . ($hasAnyShiftSchedule ? 'YES' : 'NO') . "\n\n";

// Get all shift schedules
$schedules = App\Models\ShiftSchedule::where('user_id', $user->id)
    ->with('shiftCode')
    ->orderBy('date', 'desc')
    ->limit(5)
    ->get();

echo "=== SHIFT SCHEDULES (Last 5) ===\n";
foreach ($schedules as $schedule) {
    echo "Date: {$schedule->date} | Shift: {$schedule->shift_code}\n";
    if ($schedule->shiftCode) {
        echo "  Name: {$schedule->shiftCode->name}\n";
        echo "  Time: {$schedule->shiftCode->start_time} - {$schedule->shiftCode->end_time}\n";
    }
}

// Get today's shift
echo "\n=== TODAY'S SHIFT ===\n";
$today = date('Y-m-d');
$todaySchedule = App\Models\ShiftSchedule::where('user_id', $user->id)
    ->where('date', $today)
    ->with('shiftCode')
    ->first();

if ($todaySchedule) {
    echo "Date: {$todaySchedule->date}\n";
    echo "Shift Code: {$todaySchedule->shift_code}\n";
    if ($todaySchedule->shiftCode) {
        echo "Shift Name: {$todaySchedule->shiftCode->name}\n";
        echo "Start: {$todaySchedule->shiftCode->start_time}\n";
        echo "End: {$todaySchedule->shiftCode->end_time}\n";
    }
} else {
    echo "No schedule for today ({$today})\n";
}

// Test ShiftService
echo "\n=== SHIFT SERVICE TEST ===\n";
$shiftService = app(\App\Services\ShiftService::class);
$shift = $shiftService->getMyShiftToday($user->id);

if ($shift) {
    echo "Shift Service Result:\n";
    print_r($shift);
} else {
    echo "Shift Service returned NULL\n";
}
