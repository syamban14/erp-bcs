<?php

namespace App\Models;

use App\Models\Concerns\HasApprovalFlow;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OutstationRequest extends Model
{
    use HasFactory, HasApprovalFlow;

    protected $fillable = [
        'user_id',
        'task_type',
        'start_date',
        'start_time',
        'end_date',
        'end_time',
        'location',
        'description',
        'latitude',
        'longitude',
        'attachment_path',
        'status',
        'current_approval_level',
        'manager_approved_by',
        'manager_approved_at',
        'admin_approved_by',
        'admin_approved_at',
        'rejection_reason',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'manager_approved_at' => 'datetime',
        'admin_approved_at' => 'datetime',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    public function user()
    {
        return $this->belongsTo(MPresensi::class, 'user_id');
    }

    public function managerApprover()
    {
        return $this->belongsTo(MPresensi::class, 'manager_approved_by');
    }

    public function adminApprover()
    {
        return $this->belongsTo(MPresensi::class, 'admin_approved_by');
    }

    /**
     * Get attachment URL
     */
    public function getAttachmentUrlAttribute()
    {
        return $this->attachment_path 
            ? asset('storage/' . $this->attachment_path) 
            : null;
    }
}
