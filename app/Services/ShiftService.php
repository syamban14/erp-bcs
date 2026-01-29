<?php

namespace App\Services;

use App\Models\ShiftSchedule;
use App\Models\ShiftCode;
use Carbon\Carbon;

class ShiftService
{
    /**
     * Get shift schedule untuk user hari ini
     */
    public function getMyShiftToday($userId)
    {
        $today = now()->format('Y-m-d');
        
        $schedule = ShiftSchedule::where('user_id', $userId)
            ->where('date', $today)
            ->with('shiftCode')
            ->first();
        
        if (!$schedule || !$schedule->shiftCode) {
            return null;
        }
        
        return [
            'date' => $today,
            'shift_code' => $schedule->shift_code,
            'shift_name' => $schedule->shiftCode->name,
            'time_in' => substr($schedule->shiftCode->time_in, 0, 5),
            'time_out' => substr($schedule->shiftCode->time_out, 0, 5),
            'is_off' => $schedule->shiftCode->is_off,
        ];
    }
    
    /**
     * Get shift schedule untuk 7 hari ke depan
     */
    public function getMyShiftWeek($userId)
    {
        $startDate = now()->format('Y-m-d');
        $endDate = now()->addDays(6)->format('Y-m-d');
        
        $schedules = ShiftSchedule::where('user_id', $userId)
            ->whereBetween('date', [$startDate, $endDate])
            ->with('shiftCode')
            ->orderBy('date')
            ->get();
        
        $result = [];
        for ($i = 0; $i < 7; $i++) {
            $date = now()->addDays($i);
            $dateStr = $date->format('Y-m-d');
            
            $schedule = $schedules->firstWhere('date', $dateStr);
            
            if ($schedule && $schedule->shiftCode) {
                $result[] = [
                    'date' => $dateStr,
                    'day' => $date->format('D'),
                    'shift_code' => $schedule->shift_code,
                    'shift_name' => $schedule->shiftCode->name,
                    'time_in' => substr($schedule->shiftCode->time_in, 0, 5),
                    'time_out' => substr($schedule->shiftCode->time_out, 0, 5),
                    'is_off' => $schedule->shiftCode->is_off,
                ];
            } else {
                $result[] = [
                    'date' => $dateStr,
                    'day' => $date->format('D'),
                    'shift_code' => null,
                    'shift_name' => 'No Schedule',
                    'time_in' => null,
                    'time_out' => null,
                    'is_off' => false,
                ];
            }
        }
        
        return $result;
    }
    
    /**
     * Calculate late minutes
     * Toleransi: 15 menit
     */
    public function calculateLateMinutes($timeIn, $scheduledTimeIn)
    {
        if (!$scheduledTimeIn) {
            \Log::warning('calculateLateMinutes: No scheduled time provided');
            return 0;
        }
        
        $actualTime = Carbon::createFromFormat('H:i:s', $timeIn);
        $scheduledTime = Carbon::createFromFormat('H:i:s', $scheduledTimeIn);
        
        \Log::info('calculateLateMinutes - Parsing times', [
            'timeIn_raw' => $timeIn,
            'scheduledTimeIn_raw' => $scheduledTimeIn,
            'actualTime_parsed' => $actualTime->format('H:i:s'),
            'scheduledTime_parsed' => $scheduledTime->format('H:i:s'),
            'actualTime_timestamp' => $actualTime->timestamp,
            'scheduledTime_timestamp' => $scheduledTime->timestamp
        ]);
        
        // Hitung selisih dalam menit (bisa negatif jika lebih awal)
        $diffInSeconds = $actualTime->timestamp - $scheduledTime->timestamp;
        $lateMinutes = (int) ($diffInSeconds / 60);
        
        \Log::info('calculateLateMinutes - Difference calculated', [
            'diffInSeconds' => $diffInSeconds,
            'lateMinutes' => $lateMinutes
        ]);
        
        // Jika datang lebih awal atau tepat waktu
        if ($lateMinutes <= 0) {
            \Log::info('calculateLateMinutes: On time or early');
            return 0;
        }
        
        // Toleransi 15 menit
        if ($lateMinutes <= 15) {
            \Log::info('calculateLateMinutes: Within tolerance (15 min)', ['lateMinutes' => $lateMinutes]);
            return 0;
        }
        
        \Log::info('calculateLateMinutes: Final result - LATE', ['lateMinutes' => $lateMinutes]);
        return $lateMinutes;
    }
    
    /**
     * Calculate overtime minutes
     * Toleransi: 15 menit
     */
    public function calculateOvertimeMinutes($timeOut, $scheduledTimeOut)
    {
        if (!$scheduledTimeOut) {
            return 0;
        }
        
        $actualTime = Carbon::createFromFormat('H:i:s', $timeOut);
        $scheduledTime = Carbon::createFromFormat('H:i:s', $scheduledTimeOut);
        
        // Toleransi 15 menit
        $toleranceTime = $scheduledTime->copy()->addMinutes(15);
        
        if ($actualTime->lte($toleranceTime)) {
            return 0;
        }
        
        return $actualTime->diffInMinutes($scheduledTime);
    }
    
    /**
     * Calculate working hours
     */
    public function calculateWorkingHours($timeIn, $timeOut, $shiftCode = null)
    {
        $start = Carbon::createFromFormat('H:i:s', $timeIn);
        $end = Carbon::createFromFormat('H:i:s', $timeOut);
        
        // Handle shift malam (cross-midnight)
        if ($shiftCode && str_starts_with($shiftCode, 'M')) {
            // Jika shift malam dan time_out < time_in, berarti lewat tengah malam
            if ($end->lt($start)) {
                $end->addDay();
            }
        }
        
        $diffInMinutes = $start->diffInMinutes($end);
        return round($diffInMinutes / 60, 2);
    }
}
