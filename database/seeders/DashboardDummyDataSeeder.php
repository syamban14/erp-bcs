<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MPresensi;
use App\Models\Presence;
use App\Models\Leave;
use App\Models\PermissionRequest;
use App\Models\AttendanceCorrection;
use App\Models\OutstationRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardDummyDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🚀 Starting Dashboard Dummy Data Seeder...');
        
        // Get all active users
        $users = MPresensi::where('is_active', true)->get();
        
        if ($users->isEmpty()) {
            $this->command->error('❌ No active users found in m_presensi table!');
            $this->command->warn('Please ensure m_presensi table has data first.');
            return;
        }
        
        $this->command->info("✅ Found {$users->count()} active users");
        
        // Seed data for last 30 days
        $this->seedPresences($users);
        $this->seedLeaves($users);
        
        // Skip these for now - schema mismatches
        // $this->seedPermissions($users);
        // $this->seedCorrections($users);
        // $this->seedOutstations($users);
        
        $this->command->info('✅ Dashboard Dummy Data Seeding Complete!');
    }
    
    /**
     * Seed presence data (clock-in/out) for last 30 days
     */
    protected function seedPresences($users)
    {
        $this->command->info('📊 Seeding Presences...');
        
        $startDate = Carbon::now()->subDays(30);
        $endDate = Carbon::now();
        
        $inserted = 0;
        
        foreach ($users as $user) {
            $currentDate = $startDate->copy();
            
            while ($currentDate->lte($endDate)) {
                // Skip weekends (Saturday = 6, Sunday = 0)
                if (in_array($currentDate->dayOfWeek, [0, 6])) {
                    $currentDate->addDay();
                    continue;
                }
                
                // 85% chance of attendance (realistic absenteeism)
                if (rand(1, 100) <= 85) {
                    // Random clock-in time between 07:30 - 09:30
                    $clockInHour = rand(7, 9);
                    $clockInMinute = rand(0, 59);
                    $clockInTime = sprintf('%02d:%02d:00', $clockInHour, $clockInMinute);
                    
                    // Calculate late minutes (if after 08:00)
                    $scheduledStartMinutes = 8 * 60;
                    $actualStartMinutes = ($clockInHour * 60) + $clockInMinute;
                    $lateMinutes = max(0, $actualStartMinutes - $scheduledStartMinutes);
                    
                    // Random clock-out time between 16:30 - 18:00
                    $clockOutHour = rand(16, 17);
                    $clockOutMinute = rand(0, 59);
                    $clockOutTime = sprintf('%02d:%02d:00', $clockOutHour, $clockOutMinute);
                    
                    // 10% chance of not clocking out yet (if today)
                    if ($currentDate->isToday() && rand(1, 100) <= 10) {
                        $clockOutTime = null;
                    }
                    
                    $workingHours = 0;
                    if ($clockOutTime) {
                        $workingHours = (($clockOutHour * 60) + $clockOutMinute - $actualStartMinutes) / 60;
                    }
                    
                    $status = $lateMinutes > 0 ? 'late' : 'present';
                    
                    // Use updateOrCreate to prevent duplicates on re-run
                    Presence::updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'date'    => $currentDate->format('Y-m-d'),
                        ],
                        [
                            'clock_in'      => $clockInTime,
                            'clock_out'     => $clockOutTime,
                            'late_minutes'  => $lateMinutes,
                            'working_hours' => round($workingHours, 2),
                            'status'        => $status,
                            'latitude_in'   => (string)(-6.2 + (rand(-100, 100) / 10000)),
                            'longitude_in'  => (string)(106.8 + (rand(-100, 100) / 10000)),
                            'latitude_out'  => $clockOutTime ? (string)(-6.2 + (rand(-100, 100) / 10000)) : null,
                            'longitude_out' => $clockOutTime ? (string)(106.8 + (rand(-100, 100) / 10000)) : null,
                            'created_at'    => $currentDate->copy()->setTime($clockInHour, $clockInMinute),
                            'updated_at'    => $clockOutTime
                                ? $currentDate->copy()->setTime($clockOutHour, $clockOutMinute)
                                : $currentDate->copy()->setTime($clockInHour, $clockInMinute),
                        ]
                    );
                    
                    $inserted++;
                }
                
                $currentDate->addDay();
            }
        }
        
        $this->command->info("   ✅ Inserted {$inserted} presence records");
    }
    
    /**
     * Seed leave requests
     */
    protected function seedLeaves($users)
    {
        $this->command->info('🏖️  Seeding Leaves...');
        
        $leaveTypes = ['annual', 'sick', 'permission', 'unpaid'];
        $statuses = ['pending', 'approved', 'rejected'];
        
        $inserted = 0;
        
        // Create 2-5 leaves per user (random subset)
        foreach ($users->random(min(15, $users->count())) as $user) {
            $leaveCount = rand(2, 5);
            
            for ($i = 0; $i < $leaveCount; $i++) {
                $type = $leaveTypes[array_rand($leaveTypes)];
                $startDate = Carbon::now()->subDays(rand(1, 30));
                $days = rand(1, 5);
                $endDate = $startDate->copy()->addDays($days - 1);
                
                // Weighted random status (more approved than pending)
                $rand = rand(1, 100);
                if ($rand <= 30) $status = 'pending';
                elseif ($rand <= 90) $status = 'approved';
                else $status = 'rejected';
                
                Leave::create([
                    'user_id' => $user->id,
                    'type' => $type,
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'reason' => "Dummy reason for {$type} leave - testing dashboard",
                    'status' => $status,
                    'created_at' => $startDate->copy()->subDays(rand(1, 7)),
                    'updated_at' => Carbon::now(),
                ]);
                
                $inserted++;
            }
        }
        
        $this->command->info("   ✅ Inserted {$inserted} leave records");
    }
    
    /**
     * Seed permission requests
     */
    protected function seedPermissions($users)
    {
        $this->command->info('📝 Seeding Permission Requests...');
        
        $statuses = ['pending', 'approved', 'rejected'];
        $inserted = 0;
        
        // Create 1-3 permissions per user
        foreach ($users->random(min(10, $users->count())) as $user) {
            $permCount = rand(1, 3);
            
            for ($i = 0; $i < $permCount; $i++) {
                $date = Carbon::now()->subDays(rand(1, 20));
                $startTime = $date->copy()->setTime(rand(8, 14), rand(0, 59));
                $endTime = $startTime->copy()->addHours(rand(1, 4));
                
                PermissionRequest::create([
                    'user_id' => $user->id,
                    'date' => $date,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'reason' => 'Dummy permission reason',
                    'status' => $statuses[array_rand($statuses)],
                    'created_at' => $date->copy()->subDays(rand(1, 5)),
                ]);
                
                $inserted++;
            }
        }
        
        $this->command->info("   ✅ Inserted {$inserted} permission records");
    }
    
    /**
     * Seed attendance corrections
     */
    protected function seedCorrections($users)
    {
        $this->command->info('🔧 Seeding Attendance Corrections...');
        
        $statuses = ['pending', 'approved', 'rejected'];
        $inserted = 0;
        
        // Create 1-2 corrections per user
        foreach ($users->random(min(8, $users->count())) as $user) {
            $corrCount = rand(1, 2);
            
            for ($i = 0; $i < $corrCount; $i++) {
                $date = Carbon::now()->subDays(rand(1, 15));
                $clockIn = $date->copy()->setTime(rand(7, 9), rand(0, 59));
                $clockOut = $clockIn->copy()->addHours(rand(8, 10));
                
                AttendanceCorrection::create([
                    'user_id' => $user->id,
                    'date' => $date,
                    'clock_in' => $clockIn,
                    'clock_out' => $clockOut,
                    'reason' => 'Dummy correction reason - forgot to clock in/out',
                    'status' => $statuses[array_rand($statuses)],
                    'created_at' => $date->copy()->addDays(rand(1, 3)),
                ]);
                
                $inserted++;
            }
        }
        
        $this->command->info("   ✅ Inserted {$inserted} correction records");
    }
    
    /**
     * Seed outstation requests
     */
    protected function seedOutstations($users)
    {
        $this->command->info('✈️  Seeding Outstation Requests...');
        
        $statuses = ['pending', 'approved', 'rejected'];
        $inserted = 0;
        
        // Create 1-2 outstation requests
        foreach ($users->random(min(5, $users->count())) as $user) {
            $startDate = Carbon::now()->subDays(rand(1, 10));
            $days = rand(2, 7);
            $endDate = $startDate->copy()->addDays($days);
            
            $status = $statuses[array_rand($statuses)];
            
            OutstationRequest::create([
                'user_id' => $user->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'location' => 'Jakarta / Surabaya / Bandung',
                'purpose' => 'Dummy business trip purpose',
                'status' => $status,
                'approved_by' => $status !== 'pending' ? 1 : null,
                'approved_at' => $status !== 'pending' ? Carbon::now()->subDays(rand(0, 3)) : null,
                'created_at' => $startDate->copy()->subDays(rand(5, 10)),
            ]);
            
            $inserted++;
        }
        
        $this->command->info("   ✅ Inserted {$inserted} outstation records");
    }
}
