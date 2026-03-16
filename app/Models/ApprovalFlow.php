<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalFlow extends Model
{
    use HasFactory;

    protected $fillable = [
        'approvable_type',
        'approvable_id',
        'level',
        'status',
        'approved_by',
        'approved_at',
        'notes',
    ];

    protected $casts = [
        'level'       => 'integer',
        'approved_at' => 'datetime',
    ];

    /**
     * Role yang dipetakan ke setiap level approval
     */
    public const LEVEL_ROLES = [
        1 => 'supervisor',
        2 => 'manager',
        3 => 'hr',
        4 => 'general_manager',
        5 => 'direktur',
    ];

    public const LEVEL_LABELS = [
        1 => 'Supervisor',
        2 => 'Manager',
        3 => 'HR',
        4 => 'General Manager',
        5 => 'Direktur',
    ];

    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    /**
     * Relasi polymorphic ke model yang memiliki approval
     */
    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * User yang melakukan approve/reject
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(MPresensi::class, 'approved_by');
    }

    /**
     * Label untuk level ini
     */
    public function getLevelLabelAttribute(): string
    {
        return self::LEVEL_LABELS[$this->level] ?? "Level {$this->level}";
    }

    /**
     * Warna badge untuk tampilan Filament
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'approved' => 'success',
            'rejected' => 'danger',
            default    => 'warning',
        };
    }
}
