<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OutstationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OutstationRequestController extends Controller
{
    /**
     * Submit outstation request
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'task_type' => 'required|in:Perjalanan Dinas,Pelatihan',
            'start_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'end_date' => 'required|date|after_or_equal:start_date',
            'end_time' => 'required|date_format:H:i',
            'location' => 'required|string|max:255',
            'description' => 'required|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120', // 5MB
        ]);

        $user = $request->user();

        // Upload file if provided
        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $filename = time() . '_' . $file->getClientOriginalName();
            $attachmentPath = $file->storeAs('outstation-attachments', $filename, 'public');
        }

        $outstation = OutstationRequest::create([
            'user_id' => $user->id,
            'task_type' => $validated['task_type'],
            'start_date' => $validated['start_date'],
            'start_time' => $validated['start_time'],
            'end_date' => $validated['end_date'],
            'end_time' => $validated['end_time'],
            'location' => $validated['location'],
            'description' => $validated['description'],
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'attachment_path' => $attachmentPath,
            'status' => 'pending',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Pengajuan tugas luar berhasil dikirim',
            'data' => [
                'id' => $outstation->id,
                'task_type' => $outstation->task_type,
                'status' => $outstation->status,
                'created_at' => $outstation->created_at,
            ]
        ], 201);
    }

    /**
     * Get user's outstation requests
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $requests = OutstationRequest::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $requests->map(fn($req) => [
                'id' => $req->id,
                'task_type' => $req->task_type,
                'start_date' => $req->start_date->format('Y-m-d'),
                'start_time' => $req->start_time,
                'end_date' => $req->end_date->format('Y-m-d'),
                'end_time' => $req->end_time,
                'location' => $req->location,
                'description' => $req->description,
                'status' => $req->status,
                'attachment_url' => $req->attachment_url,
                'rejection_reason' => $req->rejection_reason,
                'created_at' => $req->created_at,
            ])
        ]);
    }
}
