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
    use HasApiTokens, HasFactory, Notifiable, LogsActivity;

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

    public function canAccessPanel(Panel $panel): bool
    {
        // Hanya yang bukan 'user' biasa yang boleh masuk ke Admin Panel
        return in_array($this->role, ['superadmin', 'superhyperadmin', 'direktur', 'hr', 'manager', 'supervisor']);
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
