<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceCorrection;
use Illuminate\Http\Request;

class CorrectionController extends Controller
{
    /**
     * Get correction history
     */
    /**
     * Get correction history
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $corrections = AttendanceCorrection::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($correction) {
                return [
                    'id' => $correction->id,
                    'date' => $correction->date->format('Y-m-d'),
                    'type' => $correction->type,
                    'time' => $correction->time,
                    'reason' => $correction->reason,
                    'status' => $correction->status,
                    'evidence_url' => $correction->evidence_url, // Added
                    'created_at' => $correction->created_at->toISOString(),
                ];
            });
        
        return response()->json([
            'message' => 'Success',
            'data' => $corrections
        ]);
    }
    
    /**
     * Submit correction request
     */
    public function store(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'type' => 'required|in:in,out',
            'time' => 'required|date_format:H:i:s,H:i',
            'reason' => 'required|string',
            'evidence' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048', // max 2MB
        ]);
        
        $user = $request->user();
        
        // Handle file upload
        $evidencePath = null;
        if ($request->hasFile('evidence')) {
            $evidencePath = $request->file('evidence')->store('corrections', 'public');
        }
        
        // Normalize time format to HH:mm:ss
        $time = $request->time;
        if (strlen($time) === 5) { // HH:mm
            $time .= ':00';
        }
        
        $correction = AttendanceCorrection::create([
            'user_id' => $user->id,
            'date' => $request->date,
            'type' => $request->type,
            'time' => $time,
            'reason' => $request->reason,
            'evidence' => $evidencePath,
            'status' => 'pending',
        ]);
        
        return response()->json([
            'message' => 'Correction request submitted successfully',
            'data' => [
                'id' => $correction->id,
                'status' => $correction->status,
                'date' => $correction->date->format('Y-m-d'),
            ]
        ], 201);
    }
}
