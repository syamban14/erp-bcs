<?php

namespace App\Observers;

use App\Models\PermissionRequest;
use App\Models\Notification;

class PermissionRequestObserver
{
    /**
     * Handle the PermissionRequest "updated" event.
     */
    public function updated(PermissionRequest $permission): void
    {
        // Only trigger when status changes from pending
        if ($permission->isDirty('status') && $permission->status !== 'pending') {
            
            $title = "Pengajuan Izin " . ucfirst($permission->status);
            
            $type = match($permission->status) {
                'approved' => 'success',
                'rejected' => 'error',
                default => 'info'
            };
            
            $message = "Pengajuan izin " . $permission->type . 
                       " telah " . strtoupper($permission->status) . ".";

            Notification::create([
                'user_id' => $permission->user_id,
                'title'   => $title,
                'message' => $message,
                'type'    => $type,
                'reference_id' => $permission->id,
                'reference_type' => 'permission',
            ]);
        }
    }
}
