<?php

namespace App\Http\Controllers;

use App\Models\PermissionRequest;
use App\Models\AttendanceCorrection;
use Illuminate\Http\Request;

class ApprovalCenterController extends Controller
{
    /**
     * Get all pending permission requests
     */
    public function getPermissions()
    {
        $permissions = PermissionRequest::with('user')
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => $permissions
        ]);
    }
    
    /**
     * Get all pending attendance corrections
     */
    public function getCorrections()
    {
        $corrections = AttendanceCorrection::with('user')
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => $corrections
        ]);
    }
    
    /**
     * Get all pending leaves
     */
    public function getLeaves()
    {
        $leaves = \App\Models\Leave::with('user')
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => $leaves
        ]);
    }
}
