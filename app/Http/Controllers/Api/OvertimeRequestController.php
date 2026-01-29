<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OvertimeRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OvertimeRequestController extends Controller
{
    /**
     * Store overtime request
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date|date_format:Y-m-d',
            'end_date' => 'required|date|date_format:Y-m-d|after_or_equal:start_date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'description' => 'required|string|max:1000',
            'attachment' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120', // 5MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        // Upload file
        $file = $request->file('attachment');
        $filename = time() . '_' . $file->getClientOriginalName();
        $path = $file->storeAs('overtime-attachments', $filename, 'public');

        // Create overtime request
        $overtime = OvertimeRequest::create([
            'user_id' => $request->user()->id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'description' => $request->description,
            'attachment_path' => $path,
            'status' => 'pending',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Pengajuan lembur berhasil dikirim',
            'data' => [
                'id' => $overtime->id,
                'status' => $overtime->status,
                'created_at' => $overtime->created_at->toIso8601String(),
            ]
        ], 201);
    }
    
    /**
     * Get overtime request history
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $overtimes = OvertimeRequest::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($overtime) {
                return [
                    'id' => $overtime->id,
                    'start_date' => $overtime->start_date->format('Y-m-d'),
                    'end_date' => $overtime->end_date->format('Y-m-d'),
                    'start_time' => $overtime->start_time,
                    'end_time' => $overtime->end_time,
                    'description' => $overtime->description,
                    'total_hours' => $overtime->calculateOvertimeHours(),
                    'status' => $overtime->status,
                    'attachment_url' => $overtime->attachment_url,
                    'rejection_reason' => $overtime->rejection_reason,
                    'created_at' => $overtime->created_at->toIso8601String(),
                ];
            });
        
        return response()->json([
            'status' => 'success',
            'data' => $overtimes
        ]);
    }
}
