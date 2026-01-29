<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== MIGRATING QUOTA DATA TO LEAVE_BALANCES ===\n\n";

// Get all users
$users = App\Models\User::all();
$currentYear = date('Y');

echo "Found {$users->count()} users\n";
echo "Current year: {$currentYear}\n\n";

// Get all approved leaves to calculate used quota
$approvedLeaves = App\Models\Leave::where('status', 'approved')->get();

echo "Found {$approvedLeaves->count()} approved leaves\n\n";

// Calculate used quota per user for current year
$usedQuotaPerUser = [];
foreach ($approvedLeaves as $leave) {
    if ($leave->isLeaveType()) {
        $year = \Carbon\Carbon::parse($leave->start_date)->year;
        $days = $leave->calculateLeaveDays();
        
        if (!isset($usedQuotaPerUser[$leave->user_id])) {
            $usedQuotaPerUser[$leave->user_id] = [];
        }
        if (!isset($usedQuotaPerUser[$leave->user_id][$year])) {
            $usedQuotaPerUser[$leave->user_id][$year] = 0;
        }
        
        $usedQuotaPerUser[$leave->user_id][$year] += $days;
    }
}

echo "=== CREATING LEAVE BALANCES ===\n";

foreach ($users as $user) {
    // Create balance for current year
    $used = $usedQuotaPerUser[$user->id][$currentYear] ?? 0;
    
    $balance = App\Models\LeaveBalance::create([
        'user_id' => $user->id,
        'year' => $currentYear,
        'quota' => 12,
        'used' => $used,
    ]);
    
    echo "✓ User: {$user->name} | Year: {$currentYear} | Quota: 12 | Used: {$used} | Remaining: " . $balance->getRemainingQuota() . "\n";
    
    // Create balances for previous years if there are approved leaves
    if (isset($usedQuotaPerUser[$user->id])) {
        foreach ($usedQuotaPerUser[$user->id] as $year => $usedDays) {
            if ($year != $currentYear) {
                $balancePrev = App\Models\LeaveBalance::create([
                    'user_id' => $user->id,
                    'year' => $year,
                    'quota' => 12,
                    'used' => $usedDays,
                ]);
                echo "  └─ Year: {$year} | Used: {$usedDays} | Remaining: " . $balancePrev->getRemainingQuota() . "\n";
            }
        }
    }
}

echo "\n=== MIGRATION COMPLETE ===\n";
echo "Total leave_balances created: " . App\Models\LeaveBalance::count() . "\n";
