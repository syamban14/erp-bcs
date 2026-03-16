<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FatigueTest extends Model
{
    use HasFactory;

    protected $connection = 'pgsql'; // presensi_db
    
    protected $fillable = [
        'user_id', // Changed from employee_id
        'test_datetime',
        'memory_score',
        'sleep_time',
        'reaction_avg_ms',
        'reaction_times',
        'fatigue_level',
        'is_retry',
        'retry_after',
    ];
    
    protected $casts = [
        'test_datetime' => 'datetime',
        'sleep_time' => 'datetime:H:i',
        'reaction_times' => 'array',
        'is_retry' => 'boolean',
        'retry_after' => 'datetime',
    ];
    
    /**
     * Relation to MPresensi (cross-database)
     */
    public function user()
    {
        return $this->belongsTo(MPresensi::class, 'user_id');
    }
    
    /**
     * Scope: Filter tests for today
     */
    public function scopeToday($query)
    {
        return $query->whereDate('test_datetime', today());
    }
    
    /**
     * Scope: Filter by user
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
    
    /**
     * Scope: Filter by fatigue level
     */
    public function scopeByLevel($query, $level)
    {
        return $query->where('fatigue_level', $level);
    }
    
    /**
     * Accessor: Can the employee work?
     */
    public function getCanWorkAttribute(): bool
    {
        return in_array($this->fatigue_level, ['normal', 'moderate']);
    }
    
    /**
     * Accessor: Can retry now?
     */
    public function getCanRetryNowAttribute(): bool
    {
        if ($this->fatigue_level !== 'severe' || !$this->retry_after) {
            return false;
        }
        return now()->gte($this->retry_after);
    }
    
    /**
     * Accessor: Minutes until can retry
     */
    public function getRetryCountdownMinutesAttribute(): ?int
    {
        if (!$this->retry_after || $this->can_retry_now) {
            return null;
        }
        return now()->diffInMinutes($this->retry_after, false);
    }
    
    /**
     * Check if user has tested today
     */
    public static function hasTestedToday(int $userId): bool
    {
        return static::forUser($userId)
            ->today()
            ->exists();
    }
    
    /**
     * Get latest test for user today
     */
    public static function getLatestToday(int $userId): ?self
    {
        return static::forUser($userId)
            ->today()
            ->latest('test_datetime')
            ->first();
    }
    
    /**
     * Count tests for user today
     */
    public static function countToday(int $userId): int
    {
        return static::forUser($userId)
            ->today()
            ->count();
    }
}
