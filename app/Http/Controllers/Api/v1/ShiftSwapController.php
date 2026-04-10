<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\ShiftSwapService;
use App\Models\ShiftSwapRequest;
use App\Models\ShiftSchedule;
use Illuminate\Http\Request;

class ShiftSwapController extends Controller
{
    protected $swapService;
    
    public function __construct(ShiftSwapService $swapService)
    {
        $this->swapService = $swapService;
    }
    
    /**
     * Get list of employees eligible for shift swap
     * Returns employees who have shift schedule on the same date
     */
    public function getEligibleEmployees(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
        ]);
        
        $user = $request->user();
        $date = $request->date;
        
        // Get my shift schedule for the date
        $mySchedule = ShiftSchedule::where('user_id', $user->id)
            ->where('date', $date)
            ->first();
        
        if (!$mySchedule) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki jadwal shift pada tanggal tersebut'
            ], 400);
        }
        
        // Get other employees who have shift schedule on the same date
        $employees = ShiftSchedule::where('date', $date)
            ->where('user_id', '!=', $user->id)
            ->with(['user.karyawan', 'shiftCode'])
            ->get()
            ->map(function($schedule) {
                return [
                    'user_id' => $schedule->user_id,
                    'name' => $schedule->user->name ?? $schedule->user->karyawan->nama ?? 'Unknown',
                    'shift_code' => $schedule->shift_code,
                    'shift_name' => $schedule->shiftCode->name ?? $schedule->shift_code,
                    'time_in' => $schedule->shiftCode->time_in ?? null,
                    'time_out' => $schedule->shiftCode->time_out ?? null,
                ];
            });
        
        return response()->json([
            'success' => true,
            'message' => 'Eligible employees retrieved successfully',
            'data' => [
                'my_shift' => [
                    'shift_code' => $mySchedule->shift_code,
                    'shift_name' => $mySchedule->shiftCode->name ?? $mySchedule->shift_code,
                    'time_in' => $mySchedule->shiftCode->time_in ?? null,
                    'time_out' => $mySchedule->shiftCode->time_out ?? null,
                ],
                'employees' => $employees
            ]
        ]);
    }
    
    /**
     * Get my swap requests (as requester or target)
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $swaps = ShiftSwapRequest::where('requester_id', $user->id)
            ->orWhere('target_id', $user->id)
            ->with(['requester', 'target', 'approver', 'requesterShift', 'targetShift'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);
            
        return response()->json($swaps);
    }
    
    /**
     * Request shift swap
     */
    public function store(Request $request)
    {
        $request->validate([
            'target_employee_id' => 'required|exists:m_presensi,id',
            'original_date' => 'required|date',
            'target_date' => 'required|date',
            'original_shift_id' => 'required|exists:shift_codes,id',
            'target_shift_id' => 'required|exists:shift_codes,id',
            'reason' => 'nullable|string',
        ]);
        
        try {
            $swap = $this->swapService->requestSwap(
                $request->user()->id,
                $request->target_employee_id,
                $request->original_date,
                $request->target_date,
                $request->reason,
                $request->original_shift_id,
                $request->target_shift_id
            );
            
            return response()->json([
                'meta' => [
                    'code' => 201,
                    'status' => 'success',
                    'message' => 'Pengajuan pertukaran shift berhasil dikirim dan menunggu persetujuan.'
                ],
                'data' => null
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'meta' => [
                    'code' => 422,
                    'status' => 'error',
                    'message' => $e->getMessage()
                ],
                'data' => null
            ], 422);
        }
    }
    
    /**
     * Get swap request detail
     */
    public function show($id, Request $request)
    {
        $user = $request->user();
        
        $swap = ShiftSwapRequest::where('id', $id)
            ->where(function($q) use ($user) {
                $q->where('requester_id', $user->id)
                  ->orWhere('target_id', $user->id);
            })
            ->with(['requester', 'target', 'approver', 'requesterShift', 'targetShift'])
            ->first();
            
        if (!$swap) {
            return response()->json([
                'success' => false,
                'message' => 'Request tidak ditemukan'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $swap
        ]);
    }
    
    /**
     * Cancel swap request (only by requester, only if pending)
     */
    public function destroy($id, Request $request)
    {
        try {
            $this->swapService->cancelSwap($id, $request->user()->id);
            
            return response()->json([
                'success' => true,
                'message' => 'Request tukar shift berhasil dibatalkan'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
