<?php

namespace App\Models;

use App\Models\Concerns\HasApprovalFlow;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Leave extends Model
{
    use HasFactory, HasApprovalFlow, LogsActivity;

    protected $fillable = [
        'user_id',
        'type',
        'start_date',
        'end_date',
        'reason',
        'status',
        'current_approval_level',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'attachment_path',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_at' => 'datetime',
    ];

    /**
     * Get attachment URL
     */
    public function getAttachmentUrlAttribute()
    {
        return $this->attachment_path 
            ? asset('storage/' . $this->attachment_path) 
            : null;
    }

    public function user()
    {
        return $this->belongsTo(MPresensi::class);
    }
    
    public function approver()
    {
        return $this->belongsTo(MPresensi::class, 'approved_by');
    }
    
    /**
     * Calculate number of leave days
     */
    public function calculateLeaveDays(): int
    {
        if (!$this->start_date || !$this->end_date) {
            return 0;
        }
        
        $start = \Carbon\Carbon::parse($this->start_date);
        $end = \Carbon\Carbon::parse($this->end_date);
        
        return $start->diffInDays($end) + 1;
    }
    
    /**
     * Check if this is a leave type request that deducts annual quota (cuti tahunan)
     * Cuti Spesial TIDAK memotong quota
     */
    public function isLeaveType(): bool
    {
        $type = strtolower($this->type);
        
        // Hanya cuti tahunan yang memotong quota
        $annualLeaveTypes = ['tahunan', 'annual', 'annual_leave', 'cuti_tahunan', 'cuti tahunan'];
        
        return in_array($type, $annualLeaveTypes);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
