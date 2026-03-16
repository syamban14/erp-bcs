<?php

namespace App\Models;

use App\Models\Concerns\HasApprovalFlow;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceCorrection extends Model
{
    use HasFactory, HasApprovalFlow;

    protected $fillable = [
        'user_id',
        'date',
        'type',
        'time',
        'reason',
        'evidence',
        'status',
        'current_approval_level',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];

    protected $casts = [
        'date' => 'date',
        'approved_at' => 'datetime',
    ];

    /**
     * Get evidence URL
     */
    public function getEvidenceUrlAttribute()
    {
        return $this->evidence 
            ? asset('storage/' . $this->evidence) 
            : null;
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\MPresensi::class);
    }

    public function approver()
    {
        return $this->belongsTo(\App\Models\MPresensi::class, 'approved_by');
    }
}
