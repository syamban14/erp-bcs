<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class MPresensi extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $connection = 'pgsql_master';
    protected $table = 'm_presensi';

    protected $fillable = [
        'karyawan_id',
        'name',
        'email',
        'password',
        'photo',
        'device_token',
        'is_active',
        'role',
        'employment_type',
        'office_location_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
        'is_active' => 'boolean',
    ];

    public function karyawan()
    {
        return $this->belongsTo(MKaryawan::class, 'karyawan_id');
    }
    
    public function officeLocation()
    {
        return $this->belongsTo(\App\Models\OfficeLocation::class, 'office_location_id');
    }
}
