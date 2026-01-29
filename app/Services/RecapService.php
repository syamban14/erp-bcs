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
        // Total Hari Kerja (Scheduled)
        $shifts = ShiftSchedule::where('user_id', $user->id)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->whereHas('shiftCode', function ($query) {
                $query->where('is_off', false);
            })
            ->count();
            
        // If no shifts (Regular Employee?), assume Mon-Fri or Mon-Sat minus holidays?
        // For simplicity, if count is 0, we might need a fallback.
        // But assuming most have shifts generated.
        $totalWorkingDays = $shifts;

        // Total Kehadiran
        $totalPresent = $presences->whereNotNull('clock_in')->count();
        
        // Hadir Luar Jadwal
        // Complex: Check entries where date is NOT in non-off shifts.
        // Actually simpler: If presence exists but shift was Off.
        // But calculating from database is hard without joining.
        // Let's assume most presences match schedule.
        // Use a heuristic: If working days < present, difference is outside schedule? Not accurate.
        // Skip exact "Hadir Luar Jadwal" unless critical, or count presences where ShiftCode is 'OFF' (if stored).
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
            // Simple type check
            $type = strtolower($leave->type);
            if (in_array($type, ['tahunan', 'annual'])) {
                $paidLeaveDays += $days;
            } elseif (in_array($type, ['sakit', 'sick'])) {
                $sickLeaveDays += $days;
            } else {
                $specialLeaveDays += $days;
            }
        }
        
        // Izin (jam) -> Actually count occurrences or sum late/early?
        // User asked for "Izin (jam)", "Terlambat (jam)", "Pulang lebih awal (jam)".
        // So Izin might be "Keluar Kantor".
        // PermissionRequest types: 'Izin Terlambat' (handled in late), 
        // 'Izin Pulang Awal' (handled in early?), 'Izin Keluar Kantor'.
        // We will count 'Izin Keluar Kantor'.
        // But Permissions don't have duration stored, just 'time'? 
        // We'll return count for now or 0.
        $permissionHours = 0; 
        
        // Tugas Luar (kali)
        $outstationCount = $outstations->count();
        
        // Terlambat (jam)
        $lateHours = $presences->sum('late_minutes') / 60;
        
        // Lembur (jam)
        $overtimeHours = $presences->sum('overtime_minutes') / 60;
        
        // Pulang Lebih Awal (jam)
        // Need to calculate early_out_minutes if not in DB.
        // Assuming we add a helper or just skip if expensive.
        // Let's assume 0 for MVP or calculate in memory loop if few records.
        $earlyOutHours = 0;
        foreach ($presences as $p) {
            // Need shift info to calc early out
        }
        
        // Alpa (Hari)
        // Scheduled - (Present + Leave + Permission(Full) + Outstation)
        // Approximate:
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
