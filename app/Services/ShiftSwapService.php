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
    public function requestSwap($requesterId, $targetId, $requesterDate, $targetDate, $reason = null, $originalShiftId = null, $targetShiftId = null)
    {
        $originalShift = \App\Models\ShiftCode::find($originalShiftId);
        $targetShift = \App\Models\ShiftCode::find($targetShiftId);

        if (!$originalShift || !$targetShift) {
            throw new \Exception('Kode shift tidak valid');
        }

        if ($requesterId == $targetId) {
            throw new \Exception('Anda tidak dapat bertukar shift dengan diri sendiri');
        }

        // Validate date (tidak boleh tanggal yang sudah lewat)
        if ($requesterDate < now()->format('Y-m-d') || $targetDate < now()->format('Y-m-d')) {
            throw new \Exception('Tidak bisa tukar shift untuk tanggal yang sudah lewat');
        }

        // Cek cuti/dinas API Requester melalui Central OverlapValidator 
        $requesterConflict = \App\Services\OverlapValidator::check($requesterId, $requesterDate, $requesterDate);
        if ($requesterConflict) {
            throw new \Exception('Gagal: Anda (Pihak Pengaju) terdeteksi bentrok (Cuti/Dinas/Tugas) pada ' . $requesterDate . '. Catatan: ' . $requesterConflict);
        }

        // Cek cuti/dinas API Target melalui Central OverlapValidator
        $targetConflict = \App\Services\OverlapValidator::check($targetId, $targetDate, $targetDate);
        if ($targetConflict) {
            throw new \Exception('Gagal: Rekan target pengganti Anda sedang Cuti/Dinas/Bentrok pada ' . $targetDate . '. Info: ' . $targetConflict);
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
            throw new \Exception('Sudah ada request tukar shift yang pending untuk jadwal ini');
        }
        
        // Create swap request
        return ShiftSwapRequest::create([
            'requester_id' => $requesterId,
            'requester_date' => $requesterDate,
            'requester_shift_code' => $originalShift->code,
            'target_id' => $targetId,
            'target_date' => $targetDate,
            'target_shift_code' => $targetShift->code,
            'reason' => $reason,
            'status' => 'pending',
            'current_approval_level' => 1,
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
