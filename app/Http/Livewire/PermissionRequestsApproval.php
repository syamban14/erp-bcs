<?php

namespace App\Http\Livewire;

use App\Models\PermissionRequest;
use Livewire\Component;
use Filament\Notifications\Notification;

class PermissionRequestsApproval extends Component
{
    public $showApproveModal = false;
    public $showRejectModal = false;
    public $selectedId = null;
    public $rejectionReason = '';

    public function render()
    {
        $requests = PermissionRequest::with('user')
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('livewire.permission-requests-approval', [
            'requests' => $requests
        ]);
    }

    public function openApproveModal($id)
    {
        $this->selectedId = $id;
        $this->showApproveModal = true;
    }

    public function openRejectModal($id)
    {
        $this->selectedId = $id;
        $this->rejectionReason = '';
        $this->showRejectModal = true;
    }

    public function approve()
    {
        $request = PermissionRequest::find($this->selectedId);
        
        if (!$request || $request->status !== 'pending') {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('Request not found or already processed')
                ->send();
            return;
        }

        $request->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        Notification::make()
            ->success()
            ->title('Permission Approved')
            ->body('Permission request has been approved successfully')
            ->send();

        $this->showApproveModal = false;
        $this->selectedId = null;
    }

    public function reject()
    {
        $this->validate([
            'rejectionReason' => 'required|min:3',
        ]);

        $request = PermissionRequest::find($this->selectedId);
        
        if (!$request || $request->status !== 'pending') {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('Request not found or already processed')
                ->send();
            return;
        }

        $request->update([
            'status' => 'rejected',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'rejection_reason' => $this->rejectionReason,
        ]);

        Notification::make()
            ->success()
            ->title('Permission Rejected')
            ->body('Permission request has been rejected')
            ->send();

        $this->showRejectModal = false;
        $this->selectedId = null;
        $this->rejectionReason = '';
    }
}
