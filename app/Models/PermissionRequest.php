<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PermissionRequest extends Model
{
    use HasFactory;

    protected $table = 'permission_requests';

    protected $fillable = [
        'user_id',
        'type',
        'start_date',
        'end_date',
        'reason',
        'time',
        'status',
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
        return $this->belongsTo(User::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
    
    /**
     * Calculate number of leave days (working days between start and end date)
     */
    public function calculateLeaveDays(): int
    {
        if (!$this->start_date || !$this->end_date) {
            return 0;
        }
        
        $start = \Carbon\Carbon::parse($this->start_date);
        $end = \Carbon\Carbon::parse($this->end_date);
        
        // Count all days including start and end date
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
        $annualLeaveTypes = ['tahunan', 'annual', 'annual_leave', 'cuti_tahunan'];
        
        return in_array($type, $annualLeaveTypes);
    }
}
