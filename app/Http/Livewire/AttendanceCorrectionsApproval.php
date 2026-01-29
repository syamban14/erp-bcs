<?php

namespace App\Http\Livewire;

use App\Models\AttendanceCorrection;
use Livewire\Component;
use Filament\Notifications\Notification;

class AttendanceCorrectionsApproval extends Component
{
    public $showApproveModal = false;
    public $showRejectModal = false;
    public $selectedId = null;
    public $rejectionReason = '';

    public function render()
    {
        $corrections = AttendanceCorrection::with('user')
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('livewire.attendance-corrections-approval', [
            'corrections' => $corrections
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
        $correction = AttendanceCorrection::find($this->selectedId);
        
        if (!$correction || $correction->status !== 'pending') {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('Correction not found or already processed')
                ->send();
            return;
        }

        $correction->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        // TODO: Update actual presence table if needed

        Notification::make()
            ->success()
            ->title('Correction Approved')
            ->body('Attendance correction has been approved successfully')
            ->send();

        $this->showApproveModal = false;
        $this->selectedId = null;
    }

    public function reject()
    {
        $this->validate([
            'rejectionReason' => 'required|min:3',
        ]);

        $correction = AttendanceCorrection::find($this->selectedId);
        
        if (!$correction || $correction->status !== 'pending') {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('Correction not found or already processed')
                ->send();
            return;
        }

        $correction->update([
            'status' => 'rejected',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'rejection_reason' => $this->rejectionReason,
        ]);

        Notification::make()
            ->success()
            ->title('Correction Rejected')
            ->body('Attendance correction has been rejected')
            ->send();

        $this->showRejectModal = false;
        $this->selectedId = null;
        $this->rejectionReason = '';
    }
}
