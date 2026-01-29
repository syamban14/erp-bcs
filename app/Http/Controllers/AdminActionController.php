<?php

namespace App\Http\Controllers;

use App\Models\PermissionRequest;
use App\Models\AttendanceCorrection;
use Illuminate\Http\Request;

class AdminActionController extends Controller
{
    /**
     * Approve permission request
     */
    public function approvePermission($id)
    {
        $permission = PermissionRequest::with('user')->findOrFail($id);
        
        if ($permission->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya permintaan dengan status pending yang dapat disetujui'
            ], 400);
        }
        
        // Check if this is a leave request and validate quota
        if ($permission->isLeaveType()) {
            $leaveDays = $permission->calculateLeaveDays();
            $year = \Carbon\Carbon::parse($permission->start_date)->year;
            
            if (!$permission->user->hasLeaveQuota($leaveDays, $year)) {
                $remaining = $permission->user->getRemainingLeaveQuota($year);
                return response()->json([
                    'success' => false,
                    'message' => "Jatah cuti tidak mencukupi. Sisa jatah: {$remaining} hari, Dibutuhkan: {$leaveDays} hari"
                ], 400);
            }
            
            // Deduct quota for the year of the permission
            $permission->user->deductLeaveQuota($leaveDays, $year);
        }
        
        $permission->update([
            'status' => 'approved',
            'approved_by' => auth()->id() ?? 1,
            'approved_at' => now(),
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Permintaan berhasil disetujui',
            'data' => $permission
        ]);
    }
    
    /**
     * Reject permission request
     */
    public function rejectPermission(Request $request, $id)
    {
        $permission = PermissionRequest::findOrFail($id);
        
        if ($permission->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending requests can be rejected'
            ], 400);
        }
        
        $permission->update([
            'status' => 'rejected',
            'approved_by' => auth()->id() ?? 1,
            'approved_at' => now(),
            'rejection_reason' => $request->input('reason', 'Rejected by admin'),
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Permission request rejected successfully',
            'data' => $permission
        ]);
    }
    
    /**
     * Approve attendance correction
     */
    public function approveCorrection($id)
    {
        $correction = AttendanceCorrection::findOrFail($id);
        
        if ($correction->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending corrections can be approved'
            ], 400);
        }
        
        $correction->update([
            'status' => 'approved',
            'approved_by' => auth()->id() ?? 1,
            'approved_at' => now(),
        ]);
        
        // TODO: Update actual presence table if needed
        
        return response()->json([
            'success' => true,
            'message' => 'Attendance correction approved successfully',
            'data' => $correction
        ]);
    }
    
    /**
     * Reject attendance correction
     */
    public function rejectCorrection(Request $request, $id)
    {
        $correction = AttendanceCorrection::findOrFail($id);
        
        if ($correction->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending corrections can be rejected'
            ], 400);
        }
        
        $correction->update([
            'status' => 'rejected',
            'approved_by' => auth()->id() ?? 1,
            'approved_at' => now(),
            'rejection_reason' => $request->input('reason', 'Rejected by admin'),
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Attendance correction rejected successfully',
            'data' => $correction
        ]);
    }
    
    /**
     * Approve leave request
     */
    public function approveLeave($id)
    {
        $leave = \App\Models\Leave::with('user')->findOrFail($id);
        
        if ($leave->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya permintaan dengan status pending yang dapat disetujui'
            ], 400);
        }
        
        // Check if this is a leave request and validate quota
        if ($leave->isLeaveType()) {
            $leaveDays = $leave->calculateLeaveDays();
            $year = \Carbon\Carbon::parse($leave->start_date)->year;
            
            if (!$leave->user->hasLeaveQuota($leaveDays, $year)) {
                $remaining = $leave->user->getRemainingLeaveQuota($year);
                return response()->json([
                    'success' => false,
                    'message' => "Jatah cuti tidak mencukupi. Sisa jatah: {$remaining} hari, Dibutuhkan: {$leaveDays} hari"
                ], 400);
            }
            
            // Deduct quota for the year of the leave
            $leave->user->deductLeaveQuota($leaveDays, $year);
        }
        
        $leave->update([
            'status' => 'approved',
            'approved_by' => auth()->id() ?? 1,
            'approved_at' => now(),
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Cuti berhasil disetujui',
            'data' => $leave
        ]);
    }
    
    /**
     * Reject leave request
     */
    public function rejectLeave(\Illuminate\Http\Request $request, $id)
    {
        $leave = \App\Models\Leave::findOrFail($id);
        
        if ($leave->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya permintaan dengan status pending yang dapat ditolak'
            ], 400);
        }
        
        $leave->update([
            'status' => 'rejected',
            'approved_by' => auth()->id() ?? 1,
            'approved_at' => now(),
            'rejection_reason' => $request->input('reason', 'Ditolak oleh admin'),
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Cuti berhasil ditolak',
            'data' => $leave
        ]);
    }
}
