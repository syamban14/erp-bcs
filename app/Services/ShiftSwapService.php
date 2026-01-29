<?php

namespace App\Services;

use App\Models\ShiftSchedule;
use App\Models\ShiftSwapRequest;
use Illuminate\Support\Facades\DB;

class ShiftSwapService
{
    /**
     * Request shift swap
     */
    public function requestSwap($requesterId, $targetId, $requesterDate, $targetDate, $reason = null)
    {
        // Validate requester shift
        $requesterSchedule = ShiftSchedule::where('user_id', $requesterId)
            ->where('date', $requesterDate)
            ->first();
            
        if (!$requesterSchedule) {
            throw new \Exception('Anda tidak memiliki jadwal shift pada tanggal tersebut');
        }
        
        // Validate target shift
        $targetSchedule = ShiftSchedule::where('user_id', $targetId)
            ->where('date', $targetDate)
            ->first();
            
        if (!$targetSchedule) {
            throw new \Exception('Karyawan target tidak memiliki jadwal shift pada tanggal tersebut');
        }
        
        // Validate date (tidak boleh tanggal yang sudah lewat)
        if ($requesterDate < now()->format('Y-m-d') || $targetDate < now()->format('Y-m-d')) {
            throw new \Exception('Tidak bisa tukar shift untuk tanggal yang sudah lewat');
        }
        
        // Check existing pending request for these shifts
        $existing = ShiftSwapRequest::where('status', 'pending')
            ->where(function($q) use ($requesterId, $targetId, $requesterDate, $targetDate) {
                $q->where(function($q2) use ($requesterId, $requesterDate) {
                    $q2->where('requester_id', $requesterId)
                       ->where('requester_date', $requesterDate);
                })->orWhere(function($q2) use ($targetId, $targetDate) {
                    $q2->where('target_id', $targetId)
                       ->where('target_date', $targetDate);
                });
            })
            ->first();
            
        if ($existing) {
            throw new \Exception('Sudah ada request swap yang pending untuk shift ini');
        }
        
        // Create swap request
        return ShiftSwapRequest::create([
            'requester_id' => $requesterId,
            'requester_date' => $requesterDate,
            'requester_shift_code' => $requesterSchedule->shift_code,
            'target_id' => $targetId,
            'target_date' => $targetDate,
            'target_shift_code' => $targetSchedule->shift_code,
            'reason' => $reason,
            'status' => 'pending',
        ]);
    }
    
    /**
     * Approve shift swap
     */
    public function approveSwap($swapId, $approverId)
    {
        $swap = ShiftSwapRequest::findOrFail($swapId);
        
        if ($swap->status !== 'pending') {
            throw new \Exception('Request ini sudah diproses');
        }
        
        DB::beginTransaction();
        try {
            // Get both schedules
            $requesterSchedule = ShiftSchedule::where('user_id', $swap->requester_id)
                ->where('date', $swap->requester_date)
                ->lockForUpdate()
                ->first();
                
            $targetSchedule = ShiftSchedule::where('user_id', $swap->target_id)
                ->where('date', $swap->target_date)
                ->lockForUpdate()
                ->first();
            
            if (!$requesterSchedule || !$targetSchedule) {
                throw new \Exception('Jadwal shift tidak ditemukan');
            }
            
            // Swap shift codes
            $tempShift = $requesterSchedule->shift_code;
            $requesterSchedule->shift_code = $targetSchedule->shift_code;
            $targetSchedule->shift_code = $tempShift;
            
            $requesterSchedule->save();
            $targetSchedule->save();
            
            // Update swap request
            $swap->status = 'approved';
            $swap->approved_by = $approverId;
            $swap->approved_at = now();
            $swap->save();
            
            DB::commit();
            return $swap;
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Reject shift swap
     */
    public function rejectSwap($swapId, $approverId, $reason = null)
    {
        $swap = ShiftSwapRequest::findOrFail($swapId);
        
        if ($swap->status !== 'pending') {
            throw new \Exception('Request ini sudah diproses');
        }
        
        $swap->status = 'rejected';
        $swap->approved_by = $approverId;
        $swap->approved_at = now();
        $swap->rejection_reason = $reason;
        $swap->save();
        
        return $swap;
    }
    
    /**
     * Cancel swap request (by requester)
     */
    public function cancelSwap($swapId, $requesterId)
    {
        $swap = ShiftSwapRequest::findOrFail($swapId);
        
        if ($swap->requester_id !== $requesterId) {
            throw new \Exception('Anda tidak berhak membatalkan request ini');
        }
        
        if ($swap->status !== 'pending') {
            throw new \Exception('Request ini sudah diproses, tidak bisa dibatalkan');
        }
        
        $swap->delete();
        
        return true;
    }
}
