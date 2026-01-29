<?php

namespace App\Observers;

use App\Models\OvertimeRequest;
use App\Models\Notification;

class OvertimeRequestObserver
{
    /**
     * Handle the OvertimeRequest "updated" event.
     */
    public function updated(OvertimeRequest $overtime): void
    {
        // Only trigger when status changes from pending
        if ($overtime->isDirty('status') && $overtime->status !== 'pending') {
            
            $title = "Pengajuan Lembur " . ucfirst($overtime->status);
            
            $type = match($overtime->status) {
                'approved' => 'success',
                'rejected' => 'error',
                default => 'info'
            };
            
            $message = "Pengajuan lembur Anda untuk tanggal " . 
                       $overtime->start_date->format('d M Y') . 
                       " telah " . strtoupper($overtime->status) . 
                       ($overtime->rejection_reason ? ". Alasan: " . $overtime->rejection_reason : ".");

            Notification::create([
                'user_id' => $overtime->user_id,
                'title'   => $title,
                'message' => $message,
                'type'    => $type,
                'reference_id' => $overtime->id,
                'reference_type' => 'overtime',
            ]);
        }
    }
}
