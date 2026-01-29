<?php

namespace App\Observers;

use App\Models\Leave;
use App\Models\Notification;

class LeaveObserver
{
    /**
     * Handle the Leave "updated" event.
     */
    public function updated(Leave $leave): void
    {
        // Only trigger when status changes from pending
        if ($leave->isDirty('status') && $leave->status !== 'pending') {
            
            $title = "Pengajuan Cuti " . ucfirst($leave->status);
            
            $type = match($leave->status) {
                'approved' => 'success',
                'rejected' => 'error',
                default => 'info'
            };
            
            $message = "Pengajuan cuti Anda untuk tanggal " . 
                       $leave->start_date->format('d M Y') . 
                       " telah " . strtoupper($leave->status) . 
                       ($leave->rejection_reason ? ". Alasan: " . $leave->rejection_reason : ".");

            Notification::create([
                'user_id' => $leave->user_id,
                'title'   => $title,
                'message' => $message,
                'type'    => $type,
                'reference_id' => $leave->id,
                'reference_type' => 'leave',
            ]);
        }
    }
}
