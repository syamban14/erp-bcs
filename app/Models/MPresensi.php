<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class MPresensi extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable, LogsActivity, \Spatie\Permission\Traits\HasRoles;

    protected $guard_name = 'web';

    protected $connection = 'pgsql_master';
    protected $table = 'm_presensi';

    protected $fillable = [
        'karyawan_id',
        'name',
        'email',
        'password',
        'photo',
        'phone',
        'address',
        'device_token',
        'is_active',
        'role',
        'employment_type',
        'office_location_id',
        'pin',
        'fcm_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'pin', // Jangan expose hash PIN ke API response
    ];

    protected $casts = [
        'password' => 'hashed',
        'is_active' => 'boolean',
    ];

    public function karyawan()
    {
        return $this->belongsTo(MKaryawan::class, 'karyawan_id');
    }

    /**
     * Override role attribute dynamically based on m_karyawan and m_atasan
     */
    public function getRoleAttribute($value)
    {
        if (in_array(strtolower($value ?? ''), ['superadmin', 'super_admin'])) {
            return 'superadmin';
        }

        // Jika atribut di database telah dioverride secara manual ke jabatan tinggi
        // (contoh: via Form 'Mobile Accounts'), maka kita wajib menghormati nilai manual ini.
        // String 'user' kita anggap sebagai 'Trigger' untuk kembali menggunakan kalkulasi Otomatis.
        if (!empty($value) && strtolower($value) !== 'user') {
            return strtolower($value);
        }

        $karyawan = $this->karyawan;
        if (!$karyawan) {
            return 'user';
        }
        
        // Cek Nama Jabatan Asli (m_title)
        $mTitle = \Illuminate\Support\Facades\DB::connection('pgsql_master')
            ->table('m_title')
            ->where('title_code', $karyawan->title)
            ->first();
            
        $jobTitle = $mTitle ? strtolower($mTitle->title) : '';

        // 1. Check Direktur
        if (stripos($jobTitle, 'direktur') !== false || stripos($jobTitle, 'ceo') !== false || stripos($karyawan->title, 'DIR') !== false) {
            return 'direktur';
        }
        
        // 2. Check Manager
        if (stripos($jobTitle, 'manager') !== false || stripos($jobTitle, 'mgr') !== false || stripos($jobTitle, 'general manager') !== false) {
            return 'manager';
        }

        // 3. Check Supervisor
        if (stripos($jobTitle, 'supervisor') !== false || stripos($jobTitle, 'spv') !== false) {
            return 'supervisor';
        }

        // 4. Check Atasan hierarchy fallback (jika judul "Head of" dll tapi punya bawahan)
        $isAtasan = \App\Models\MAtasan::where('title_atasan', $karyawan->title)->exists();
        if ($isAtasan) {
            $bawahanTitles = \App\Models\MAtasan::where('title_atasan', $karyawan->title)->pluck('title_bawahan')->toArray();
            $bawahanIsAtasan = \App\Models\MAtasan::whereIn('title_atasan', $bawahanTitles)->exists();

            if ($bawahanIsAtasan) {
                return 'manager';
            }
            return 'supervisor';
        }

        // 5. Check HR / Personalia (Staff HR yang bukan Atasan)
        if ($karyawan->dept_id === 'D_54' || stripos($jobTitle, 'human capital') !== false || stripos($jobTitle, 'personalia') !== false) {
            return 'hr';
        }

        return 'user';
    }

    /**
     * Check if user is HR or Superadmin (can see all data)
     */
    public function isGlobalAdmin(): bool
    {
        return in_array($this->role, ['superadmin', 'superhyperadmin', 'hr']) 
            || $this->hasRole(['superadmin', 'superhyperadmin']);
    }

    /**
     * Get array of all Karyawan IDs that are subordinates of this user (recursive)
     */
    public function getSubordinateKaryawanIds(): array
    {
        $myTitle = $this->karyawan?->title;
        if (!$myTitle) return [];

        $titlesToFind = [$myTitle];
        $foundTitles = [];

        // Recursive search for subordinate titles
        while (!empty($titlesToFind)) {
            $nextTitles = \App\Models\MAtasan::whereIn('title_atasan', $titlesToFind)
                ->pluck('title_bawahan')
                ->toArray();
            
            // Remove those we already found to avoid infinite loops if cycles exist
            $newTitles = array_diff($nextTitles, $foundTitles, [$myTitle]);
            
            if (empty($newTitles)) {
                break;
            }

            $foundTitles = array_merge($foundTitles, $newTitles);
            $titlesToFind = $newTitles;
        }

        if (empty($foundTitles)) {
            return [];
        }

        // Return IDs of MKaryawan who hold any of the subordinate titles
        return \App\Models\MKaryawan::whereIn('title', array_unique($foundTitles))->pluck('id')->toArray();
    }

    /**
     * Get array of all MPresensi IDs (user IDs) that are subordinates of this user
     */
    public function getSubordinateUserIds(): array
    {
        $karyawanIds = $this->getSubordinateKaryawanIds();
        
        if (empty($karyawanIds)) {
            return [];
        }

        return self::whereIn('karyawan_id', $karyawanIds)->pluck('id')->toArray();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // Hanya yang bukan 'user' biasa atau yang memiliki explicit Role spatie yang boleh masuk ke Admin Panel
        return in_array($this->role, ['superadmin', 'superhyperadmin', 'direktur', 'hr', 'manager', 'supervisor'])
            || $this->roles()->count() > 0;
    }
    
    public function officeLocation()
    {
        return $this->belongsTo(\App\Models\OfficeLocation::class, 'office_location_id');
    }

    /**
     * Semua lokasi kerja yang diperbolehkan (multi-lokasi via pivot)
     * Digunakan untuk geofencing absensi.
     */
    public function officeLocations()
    {
        return $this->belongsToMany(
            \App\Models\OfficeLocation::class,
            'user_office_locations',
            'user_id',
            'office_location_id'
        )->withTimestamps();
    }

    /**
     * Device-device yang terdaftar untuk user ini (di presensi_db)
     * Note: cross-connection, Eloquent tidak support FK constraint,
     * tapi relasi tetap bisa digunakan karena user_devices.user_id = m_presensi.id
     */
    public function devices()
    {
        return $this->hasMany(\App\Models\UserDevice::class, 'user_id');
    }

    /**
     * Relasi ke leave balances
     */
    public function leaveBalances()
    {
        return $this->hasMany(\App\Models\LeaveBalance::class, 'user_id');
    }
    
    /**
     * Get current year leave balance
     */
    public function currentYearBalance()
    {
        return $this->hasOne(\App\Models\LeaveBalance::class, 'user_id')
            ->where('year', date('Y'));
    }
    
    /**
     * Relasi ke Cuti Besar (Sabbatical Leave) yang masih aktif (berlaku)
     */
    public function activeSabbaticalLeave()
    {
        return $this->hasOne(\App\Models\SabbaticalLeave::class, 'user_id')
            ->where('expires_at', '>=', \Carbon\Carbon::now()->startOfDay())
            ->orderBy('created_at', 'desc');
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
    
    /**
     * Restore leave quota (delegate to LeaveBalance)
     */
    public function restoreLeaveQuota(int $days, int $year = null): void
    {
        $year = $year ?? date('Y');
        $balance = \App\Models\LeaveBalance::getOrCreateForUser($this, $year);
        $balance->restoreQuota($days);
    }
    
    // ==========================================
    // Helper untuk Cuti Besar (Sabbatical Leave)
    // ==========================================

    /**
     * Get remaining sabbatical leave quota
     */
    public function getRemainingSabbaticalQuota(): int
    {
        $balance = $this->activeSabbaticalLeave;
        return $balance ? $balance->getRemainingQuota() : 0;
    }
    
    /**
     * Check if user has sufficient sabbatical leave quota
     */
    public function hasSabbaticalQuota(int $days): bool
    {
        $balance = $this->activeSabbaticalLeave;
        return $balance ? $balance->hasQuota($days) : false;
    }
    
    /**
     * Deduct sabbatical leave quota
     */
    public function deductSabbaticalQuota(int $days): void
    {
        $balance = $this->activeSabbaticalLeave;
        if ($balance) {
            $balance->deductQuota($days);
        }
    }
    
    /**
     * Restore sabbatical leave quota (jika cuti dibatalkan)
     */
    public function restoreSabbaticalQuota(int $days): void
    {
        $balance = $this->activeSabbaticalLeave;
        if ($balance) {
            $balance->decrement('used', $days);
        }
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
