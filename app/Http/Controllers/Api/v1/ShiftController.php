<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\ShiftService;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    protected $shiftService;
    
    public function __construct(ShiftService $shiftService)
    {
        $this->shiftService = $shiftService;
    }
    
    /**
     * Get my shift schedule (today + week)
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $today = $this->shiftService->getMyShiftToday($user->id);
        $week = $this->shiftService->getMyShiftWeek($user->id);
        
        return response()->json([
            'success' => true,
            'data' => [
                'today' => $today,
                'week' => $week,
            ]
        ]);
    }
    
    /**
     * Get today's shift only
     */
    public function today(Request $request)
    {
        $user = $request->user();
        $shift = $this->shiftService->getMyShiftToday($user->id);
        
        return response()->json([
            'success' => true,
            'data' => $shift
        ]);
    }
    
    /**
     * Get this week's shifts
     */
    public function week(Request $request)
    {
        $user = $request->user();
        $shifts = $this->shiftService->getMyShiftWeek($user->id);
        
        return response()->json([
            'success' => true,
            'data' => $shifts
        ]);
    }
}
