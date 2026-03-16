<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\PermissionRequest;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    /**
     * List permissions (history)
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $permissions = PermissionRequest::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($permission) {
                return [
                    'id' => $permission->id,
                    'type' => $permission->type,
                    'start_date' => $permission->start_date->format('Y-m-d'),
                    'end_date' => $permission->end_date->format('Y-m-d'),
                    'reason' => $permission->reason,
                    'status' => $permission->status,
                    'time' => $permission->time,
                    'attachment_path' => $permission->attachment_path, // Added
                    'created_at' => $permission->created_at->toISOString(),
                ];
            });
        
        return response()->json([
            'meta' => [
                'code' => 200,
                'status' => 'success',
                'message' => 'List permissions retrieved'
            ],
            'data' => $permissions
        ]);
    }
    
    /**
     * Submit permission request
     */
    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:Izin Terlambat,Izin Pulang Awal,Izin Keluar Kantor',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string',
            'time' => 'required|date_format:H:i',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);
        
        $user = $request->user();

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('permissions', 'public');
        }
        
        $permission = PermissionRequest::create([
            'user_id' => $user->id,
            'type' => $request->type,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'reason' => $request->reason,
            'time' => $request->time,
            'status' => 'pending',
            'attachment_path' => $attachmentPath,
        ]);
        
        return response()->json([
            'meta' => [
                'code' => 201,
                'status' => 'success',
                'message' => 'Permission request submitted successfully'
            ],
            'data' => [
                'id' => $permission->id,
                'type' => $permission->type,
                'status' => $permission->status,
                'attachment_path' => $permission->attachment_path,
                'created_at' => $permission->created_at->toISOString(),
            ]
        ], 201);
    }
}
