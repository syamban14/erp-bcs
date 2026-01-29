<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, \Spatie\Permission\Traits\HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    
    /**
     * Relasi ke leave balances
     */
    public function leaveBalances()
    {
        return $this->hasMany(\App\Models\LeaveBalance::class);
    }
    
    /**
     * Get current year leave balance
     */
    public function currentYearBalance()
    {
        return $this->hasOne(\App\Models\LeaveBalance::class)
            ->where('year', date('Y'));
    }
    
    /**
     * Get remaining leave quota for specific year (delegate to LeaveBalance)
     */
    public function getRemainingLeaveQuota(int $year = null): int
    {
        $year = $year ?? date('Y');
        $balance = \App\Models\LeaveBalance::getOrCreateForUser($this, $year);
        return $balance->getRemainingQuota();
    }
    
    /**
     * Check if user has sufficient leave quota (delegate to LeaveBalance)
     */
    public function hasLeaveQuota(int $days, int $year = null): bool
    {
        $year = $year ?? date('Y');
        $balance = \App\Models\LeaveBalance::getOrCreateForUser($this, $year);
        return $balance->hasQuota($days);
    }
    
    /**
     * Deduct leave quota (delegate to LeaveBalance)
     */
    public function deductLeaveQuota(int $days, int $year = null): void
    {
        $year = $year ?? date('Y');
        $balance = \App\Models\LeaveBalance::getOrCreateForUser($this, $year);
        $balance->deductQuota($days);
    }
}
