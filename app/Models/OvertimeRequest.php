<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OvertimeRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'description',
        'attachment_path',
        'status',
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
        
        return asset('storage/' . $this->attachment_path);
    }
}
