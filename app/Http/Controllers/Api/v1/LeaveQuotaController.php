<?php

namespace App\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class LeaveQuotaController extends Controller
{
    /**
     * Get user's leave quota information
     */
    public function index(Request $request)
    {
        // $request->user() returns MPresensi model from API guard
        $user = $request->user();
        
        $year = $request->input('year', date('Y')); // Default: tahun sekarang
        
        // Get balance for requested year
        $balance = \App\Models\LeaveBalance::where('user_id', $user->id)
            ->where('year', $year)
            ->first();
        
        // If no balance found, return default values
        if (!$balance) {
            return response()->json([
                'meta' => [
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'Leave quota retrieved'
                ],
                'data' => [
                    'year' => (int) $year,
                    'annual_quota' => 0,
                    'used_quota' => 0,
                    'remaining_quota' => 0,
                ]
            ]);
        }
        
        return response()->json([
            'meta' => [
                'code' => 200,
                'status' => 'success',
                'message' => 'Leave quota retrieved'
            ],
            'data' => [
                'year' => $balance->year,
                'annual_quota' => $balance->quota,
                'used_quota' => $balance->used,
                'remaining_quota' => $balance->getRemainingQuota(),
            ]
        ]);
    }
    
    /**
     * Get leave quota history (all years)
     */
    public function history(Request $request)
    {
        // $request->user() returns MPresensi model from API guard
        $user = $request->user();
        
        $balances = \App\Models\LeaveBalance::where('user_id', $user->id)
            ->orderBy('year', 'desc')
            ->get()
            ->map(function($balance) {
                return [
                    'year' => $balance->year,
                    'quota' => $balance->quota,
                    'used' => $balance->used,
                    'remaining' => $balance->getRemainingQuota(),
                ];
            });
        
        return response()->json([
            'meta' => [
                'code' => 200,
                'status' => 'success',
                'message' => 'Leave quota history retrieved'
            ],
            'data' => $balances
        ]);
    }
}
