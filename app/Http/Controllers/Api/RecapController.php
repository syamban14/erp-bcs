<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Presence;
use App\Models\PermissionRequest;
use App\Models\ShiftSchedule;
use Carbon\Carbon;
use Illuminate\Http\Request;

class RecapController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2100',
        ]);

        $user = $request->user();
        $month = $request->month;
        $year = $request->year;

        // Get start and end date of the month
        $startDate = Carbon::create($year, $month, 1)->startOfDay();
        $endDate = $startDate->copy()->endOfMonth();

        // Get all presences for the month
        $presences = Presence::where('user_id', $user->id)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        // Get approved permission requests for the month
        $permissions = PermissionRequest::where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereBetween('start_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get()
            ->keyBy('start_date');

        // Get shift schedules for the month (for holidays/weekends)
        $shifts = ShiftSchedule::where('user_id', $user->id)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->with('shiftCode')
            ->get()
            ->keyBy('date');

        // Initialize summary counters
        $summary = [
            'present' => 0,
            'sick' => 0,
            'excuse' => 0,
            'alpha' => 0,
            'late' => 0,
        ];

        // Build daily history
        $dailyHistory = [];
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $dateStr = $currentDate->format('Y-m-d');
            
            // Check if there's a presence record
            $presence = $presences->get($dateStr);
            
            // Check if there's a permission record
            $permission = $permissions->get($dateStr);
            
            // Check if it's a scheduled off day
            $shift = $shifts->get($dateStr);
            $isOff = $shift && $shift->shiftCode && $shift->shiftCode->is_off;

            // Determine status and update summary
            $status = null;
            $clockIn = null;
            $clockOut = null;
            $lateMinutes = 0;
            $lateDuration = null;

            if ($presence && $presence->clock_in) {
                // Has presence record
                $clockIn = $presence->clock_in;
                $clockOut = $presence->clock_out;
                $lateMinutes = $presence->late_minutes ?? 0;

                if ($presence->status === 'Terlambat' || $lateMinutes > 0) {
                    $status = 'Terlambat';
                    $summary['late']++;
                    $lateDuration = $this->formatLateDuration($lateMinutes);
                } else {
                    $status = 'Hadir';
                }
                $summary['present']++;
            } elseif ($permission) {
                // Has approved permission
                if ($permission->type === 'sick') {
                    $status = 'Sakit';
                    $summary['sick']++;
                } else {
                    $status = 'Izin';
                    $summary['excuse']++;
                }
            } elseif ($isOff) {
                // Scheduled off day
                $status = 'Libur';
            } else {
                // No record - Alpha (only if not future date)
                if ($currentDate->lte(now())) {
                    $status = 'Alpha';
                    $summary['alpha']++;
                } else {
                    $status = '-';
                }
            }

            $dailyHistory[] = [
                'date' => $dateStr,
                'status' => $status,
                'clock_in' => $clockIn,
                'clock_out' => $clockOut,
                'late_minutes' => $lateMinutes,
                'late_duration' => $lateDuration,
            ];

            $currentDate->addDay();
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'summary' => $summary,
                'daily_history' => $dailyHistory,
            ],
        ]);
    }

    /**
     * Format late minutes to "Xj Ym" or "Xm" format
     */
    private function formatLateDuration($minutes)
    {
        if ($minutes <= 0) {
            return null;
        }

        $hours = floor($minutes / 60);
        $mins = $minutes % 60;

        if ($hours > 0) {
            if ($mins > 0) {
                return "{$hours}j {$mins}m";
            }
            return "{$hours}j";
        }

        return "{$mins}m";
    }
}
