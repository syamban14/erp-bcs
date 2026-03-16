<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\ShiftService;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    protected $shiftService;
    
    public function __construct(ShiftService $shiftService)
    {
        $this->shiftService = $shiftService;
    }
    
    /**
     * Get today's schedule
     */
    public function today(Request $request)
    {
        try {
            $user = $request->user();
            
            // Get shift schedule for today
            $shift = $this->shiftService->getMyShiftToday($user->id);
            
            // Determine employee type based on shift schedule existence
            // If user has ANY shift schedule (even for other days), they are shift employee
            $hasAnyShiftSchedule = \App\Models\ShiftSchedule::where('user_id', $user->id)->exists();
            
            $data = [
                'id' => null,
                'name' => 'Tidak Ada Jadwal',
                'start_time' => '00:00:00',
                'end_time' => '00:00:00',
                'is_shift' => false
            ];

            // Logic berdasarkan apakah karyawan shift atau regular
            if (!$hasAnyShiftSchedule) {
                // Karyawan regular - tidak ada shift schedule sama sekali
                $data = [
                    'id' => null,
                    'name' => 'Karyawan Reguler',
                    'start_time' => '08:00:00',
                    'end_time' => '17:00:00',
                    'is_shift' => false
                ];
            } elseif (!$shift) {
                // Karyawan shift tapi tidak ada schedule hari ini
                $data = [
                    'id' => null,
                    'name' => 'Tidak Ada Jadwal',
                    'start_time' => '00:00:00',
                    'end_time' => '00:00:00',
                    'is_shift' => false
                ];
            } elseif (isset($shift['is_off']) && $shift['is_off']) {
                // Off day
                $data = [
                    'id' => null,
                    'name' => 'Libur',
                    'start_time' => '00:00:00',
                    'end_time' => '00:00:00',
                    'is_shift' => false
                ];
            } else {
                // Has shift schedule
                $startTime = $shift['time_in'] ?? '00:00';
                $endTime = $shift['time_out'] ?? '00:00';
                
                // Calculate duration (handle overnight shifts)
                $start = \Carbon\Carbon::parse($startTime);
                $end = \Carbon\Carbon::parse($endTime);
                
                if ($end->lte($start)) {
                    $end->addDay();
                }
                
                $durationHours = $start->diffInHours($end, true);
                
                $data = [
                    'id' => $shift['id'] ?? null,
                    'name' => $shift['shift_name'] ?? 'Shift',
                    'start_time' => $startTime . ':00',
                    'end_time' => $endTime . ':00',
                    'duration_hours' => round($durationHours, 1),
                    'is_shift' => true
                ];
            }
            
            return response()->json([
                'meta' => [
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'Schedule retrieved successfully'
                ],
                'data' => $data
            ]);
        } catch (\Exception $e) {
            \Log::error('Schedule Error: ' . $e->getMessage() . ' Trace: ' . $e->getTraceAsString());
            return response()->json([
                'meta' => [
                    'code' => 500,
                    'status' => 'error',
                    'message' => 'Server Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine()
                ],
                'data' => null
            ], 500);
        }
    }
}
