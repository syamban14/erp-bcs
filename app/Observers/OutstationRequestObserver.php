<?php

namespace App\Observers;

use App\Models\OutstationRequest;
use App\Models\Notification;

class OutstationRequestObserver
{
    /**
     * Handle the OutstationRequest "updated" event.
     */
    public function updated(OutstationRequest $outstation): void
    {
        // Only notify on final approval or rejection (not intermediate states)
        if ($outstation->isDirty('status') && 
            in_array($outstation->status, ['approved', 'rejected'])) {
            
            $title = "Pengajuan Tugas Luar " . ucfirst($outstation->status);
            
            $type = match($outstation->status) {
                'approved' => 'success',
                'rejected' => 'error',
                default => 'info'
            };
            
            $message = "Pengajuan tugas luar ke " . $outstation->location . 
                       " telah " . strtoupper($outstation->status) . 
                       ($outstation->rejection_reason ? ". Alasan: " . $outstation->rejection_reason : ".");

            Notification::create([
                'user_id' => $outstation->user_id,
                'title'   => $title,
                'message' => $message,
                'type'    => $type,
                'reference_id' => $outstation->id,
                'reference_type' => 'outstation',
            ]);
        }
    }
}
