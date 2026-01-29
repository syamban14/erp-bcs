<?php

namespace App\Services;

use App\Models\Notification;

class NotificationService
{
    /**
     * Send notification to user
     */
    public function send($userId, $title, $message, $type = 'info', $referenceId = null, $referenceType = null)
    {
        return Notification::create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'reference_id' => $referenceId,
            'reference_type' => $referenceType,
        ]);
    }
    
    /**
     * Notify leave approved
     */
    public function notifyLeaveApproved($userId, $leaveId, $startDate)
    {
        return $this->send(
            $userId,
            'Pengajuan Cuti Disetujui',
            "Pengajuan cuti tahunan Anda tanggal {$startDate} telah disetujui.",
            'success',
            $leaveId,
            'leave_request'
        );
    }
    
    /**
     * Notify leave rejected
     */
    public function notifyLeaveRejected($userId, $leaveId, $reason)
    {
        return $this->send(
            $userId,
            'Pengajuan Cuti Ditolak',
            "Pengajuan cuti Anda ditolak. Alasan: {$reason}",
            'error',
            $leaveId,
            'leave_request'
        );
    }
    
    /**
     * Notify shift swap approved
     */
    public function notifyShiftSwapApproved($userId, $swapId, $date)
    {
        return $this->send(
            $userId,
            'Tukar Shift Disetujui',
            "Request tukar shift tanggal {$date} telah disetujui.",
            'success',
            $swapId,
            'shift_swap'
        );
    }
    
    /**
     * Notify shift swap rejected
     */
    public function notifyShiftSwapRejected($userId, $swapId, $reason)
    {
        return $this->send(
            $userId,
            'Tukar Shift Ditolak',
            "Request tukar shift Anda ditolak. Alasan: {$reason}",
            'error',
            $swapId,
            'shift_swap'
        );
    }
    
    /**
     * Notify forgot clock out
     */
    public function notifyForgotClockOut($userId)
    {
        return $this->send(
            $userId,
            'Lupa Absen Pulang',
            'Anda belum melakukan absen pulang hari ini.',
            'warning'
        );
    }
}
