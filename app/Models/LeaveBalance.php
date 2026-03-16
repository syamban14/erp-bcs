<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'year',
        'quota',
        'used',
    ];

    protected $casts = [
        'year' => 'integer',
        'quota' => 'integer',
        'used' => 'integer',
    ];

    /**
     * Relasi ke User
     */
    public function user()
    {
        return $this->belongsTo(\App\Models\MPresensi::class);
    }

    /**
     * Get remaining quota
     */
    public function getRemainingQuota(): int
    {
        return max(0, $this->quota - $this->used);
    }

    /**
     * Check if has sufficient quota
     */
    public function hasQuota(int $days): bool
    {
        return $this->getRemainingQuota() >= $days;
    }

    /**
     * Deduct quota
     */
    public function deductQuota(int $days): void
    {
        $this->increment('used', $days);
    }

    /**
     * Restore quota (jika cuti dibatalkan)
     */
    public function restoreQuota(int $days): void
    {
        $this->decrement('used', $days);
    }

    /**
     * Get or create balance for user and year
     */
    public static function getOrCreateForUser(MPresensi $user, int $year): self
    {
        return self::firstOrCreate(
            [
                'user_id' => $user->id,
                'year' => $year,
            ],
            [
                'quota' => 12, // Default quota
                'used' => 0,
            ]
        );
    }

    /**
     * Get current year balance for user
     */
    public static function getCurrentYearBalance(MPresensi $user): self
    {
        return self::getOrCreateForUser($user, date('Y'));
    }
}
