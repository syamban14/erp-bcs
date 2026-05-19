<?php

namespace App\Services;

use App\Models\MPresensi;
use App\Models\Presence;
use App\Models\Leave;
use App\Models\PermissionRequest;
use App\Models\OutstationRequest;
use App\Models\ShiftSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class RecapService
{
    /**
     * Fetch national holidays from Nager.Date API and cache it.
     * Excludes "Cuti Bersama" (Joint Leave).
     */
    public function getNationalHolidays($year)
    {
        return \Illuminate\Support\Facades\Cache::remember("national_holidays_id_{$year}", 60 * 60 * 24 * 30, function () use ($year) {
            try {
                $response = \Illuminate\Support\Facades\Http::timeout(5)->get("https://date.nager.at/api/v3/PublicHolidays/{$year}/ID");
                
                if ($response->successful()) {
                    $holidays = collect($response->json());
                    // Filter out any holiday containing "Cuti Bersama"
                    return $holidays->filter(function ($h) {
                        return stripos($h['localName'] ?? '', 'Cuti Bersama') === false;
                    })->pluck('date')->toArray();
                }
            } catch (\Exception $e) {
                // Log or ignore
            }
            return [];
        });
    }

    /**
     * Get recap data for a specific user and date range
     */
    public function getRecapData(MPresensi $user, $startDate, $endDate)
    {
        $startDate = Carbon::parse($startDate)->startOfDay();
        $endDate = Carbon::parse($endDate)->endOfDay();
        
        // 1. Get Presences
        $presences = Presence::where('user_id', $user->id)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get();
            
        // 2. Get Leaves (Approved)
        $leaves = Leave::where('user_id', $user->id)
            ->where('status', 'approved')
            ->where(function ($query) use ($startDate, $endDate) {
                // Check overlap
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate]);
            })
            ->get();
            
        // 3. Get Permissions (Approved)
        $permissions = PermissionRequest::where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereBetween('created_at', [$startDate, $endDate]) // Usually time based, close enough
            ->get();
            
        // 4. Get Outstation Requests (Approved)
        $outstations = OutstationRequest::where('user_id', $user->id)
            ->where('status', 'approved')
            ->where(function ($query) use ($startDate, $endDate) {
                 $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate]);
            })
            ->get();
            
        // 5. Calculate Metrics
        
        // Total Hari Kerja (Scheduled)
        $shifts = ShiftSchedule::where('user_id', $user->id)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->whereHas('shiftCode', function ($query) {
                $query->where('is_off', false);
            })
            ->count();
            
        // Get national holidays for the covered years
        $holidays = array_merge(
            $this->getNationalHolidays($startDate->year),
            $startDate->year !== $endDate->year ? $this->getNationalHolidays($endDate->year) : []
        );

        // Count how many of those scheduled shifts fall on a national holiday
        $shiftsOnHolidays = ShiftSchedule::where('user_id', $user->id)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->whereIn('date', $holidays)
            ->whereHas('shiftCode', function ($query) {
                $query->where('is_off', false); // Scheduled as working day
            })
            ->count();
            
        // Real working days is total scheduled non-off minus holidays that overlap with schedules
        $totalWorkingDays = max(0, $shifts - $shiftsOnHolidays);

        // Total Kehadiran
        $totalPresent = $presences->whereNotNull('clock_in')->count();
        
        // Hadir Luar Jadwal
        // (Optional: can calculate by checking if presence date is in 'holidays' or is 'is_off' shift)
        $presentOffSchedule = 0; // Placeholder
        
        // Durasi Kehadiran
        $totalDurationHours = $presences->sum('working_hours');
        
        // Cuti breakdown
        $paidLeaveDays = 0; // Tahunan
        $specialLeaveDays = 0;
        $sickLeaveDays = 0;
        
        foreach ($leaves as $leave) {
            $days = $leave->calculateLeaveDays(); // Helper model method
            
            // Adjust intersection with period if needed, but usually leave < 1 month
            $type = strtolower($leave->type);
            if (in_array($type, ['tahunan', 'annual'])) {
                $paidLeaveDays += $days;
            } elseif (in_array($type, ['sakit', 'sick'])) {
                $sickLeaveDays += $days;
            } else {
                $specialLeaveDays += $days;
            }
        }
        
        // Izin (jam)
        $permissionHours = 0; 
        
        // Tugas Luar (kali)
        $outstationCount = $outstations->count();
        
        // Terlambat (jam)
        $lateHours = $presences->sum('late_minutes') / 60;
        
        // Lembur (jam)
        $overtimeHours = $presences->sum('overtime_minutes') / 60;
        
        // Pulang Lebih Awal (jam)
        $earlyOutHours = 0;
        
        // Alpa (Hari)
        // Scheduled - (Present + Leave + Permission(Full) + Outstation)
        $alphaDays = max(0, $totalWorkingDays - ($totalPresent + $paidLeaveDays + $specialLeaveDays + $sickLeaveDays + $outstationCount));
        
        return [
            'user_id' => $user->id,
            'name' => $user->name,
            'total_hari_kerja' => $totalWorkingDays,
            'total_kehadiran' => $totalPresent,
            'hadir_luar_jadwal' => $presentOffSchedule,
            'durasi_kehadiran' => round($totalDurationHours, 1),
            'cuti_tahunan' => $paidLeaveDays,
            'cuti_special' => $specialLeaveDays,
            'cuti_sakit' => $sickLeaveDays,
            'izin_jam' => $permissionHours,
            'tugas_luar' => $outstationCount,
            'alpa' => $alphaDays,
            'lembur_jam' => round($overtimeHours, 1),
            'terlambat_jam' => round($lateHours, 1),
            'pulang_awal_jam' => round($earlyOutHours, 1),
        ];
    }
}
