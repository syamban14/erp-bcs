<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MKaryawan extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_master';
    protected $table = 'm_karyawan';
    public $timestamps = false; // Legacy table does not have created_at/updated_at

    protected $fillable = [
        'nama_karyawan', // Correct column
        'email',
        // 'nik' and 'posisi' removed as they are not found/verified yet
    ];
    
    // Allow mass assignment for now to make seeding easier given unknown full schema
    protected $guarded = [];

    public function presensiAccount()
    {
        return $this->hasOne(MPresensi::class, 'karyawan_id');
    }
}
