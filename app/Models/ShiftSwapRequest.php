<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShiftSwapRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'requester_id',
        'requester_date',
        'requester_shift_code',
        'target_id',
        'target_date',
        'target_shift_code',
        'reason',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];

    protected $casts = [
        'requester_date' => 'date',
        'target_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function requester()
    {
        return $this->belongsTo(MPresensi::class, 'requester_id');
    }

    public function target()
    {
        return $this->belongsTo(MPresensi::class, 'target_id');
    }

    public function approver()
    {
        return $this->belongsTo(MPresensi::class, 'approved_by');
    }

    public function requesterShift()
    {
        return $this->belongsTo(ShiftCode::class, 'requester_shift_code', 'code');
    }

    public function targetShift()
    {
        return $this->belongsTo(ShiftCode::class, 'target_shift_code', 'code');
    }
}
