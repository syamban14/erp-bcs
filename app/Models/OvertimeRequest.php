<?php

namespace App\Models;

use App\Models\Concerns\HasApprovalFlow;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OvertimeRequest extends Model
{
    use HasFactory, HasApprovalFlow;

    protected $fillable = [
        'user_id',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'description',
        'attachment_path',
        'status',
        'current_approval_level',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_at' => 'datetime',
    ];

    /**
     * Calculate total overtime hours
     */
    public function calculateOvertimeHours(): float
    {
        $start = \Carbon\Carbon::parse($this->start_time);
        $end = \Carbon\Carbon::parse($this->end_time);
        
        // If end time is before start time, assume it crosses midnight
        if ($end->lt($start)) {
            $end->addDay();
        }
        
        $hoursPerDay = $start->diffInHours($end, true);
        $days = $this->start_date->diffInDays($this->end_date) + 1;
        
        return $hoursPerDay * $days;
    }
    
    /**
     * Get attachment URL
     */
    public function getAttachmentUrlAttribute(): ?string
    {
        if (!$this->attachment_path) {
            return null;
        }
        
        // Menggunakan proxy file endpoint untuk bypass nginx 403 (storage symlink issue)
        return url('/api/v1/public/files/' . ltrim($this->attachment_path, '/'));
    }

    public function user()
    {
        return $this->belongsTo(MPresensi::class);
    }
    
    public function approver()
    {
        return $this->belongsTo(MPresensi::class, 'approved_by');
    }
}
