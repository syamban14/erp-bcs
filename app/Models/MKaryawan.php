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

    // protected $fillable = [
    //     'nama_karyawan', // Correct column
    //     'email',
    //     // 'nik' and 'posisi' removed as they are not found/verified yet
    // ];
    
    // Allow mass assignment for now to make seeding easier given unknown full schema
    protected $guarded = [];

    // Paksa payroll_id dibaca sebagai string agar leading zero ('0703') tidak hilang
    protected $casts = [
        'payroll_id' => 'string',
    ];

    /**
     * Boot the model.
     */
    protected static function booted()
    {
        parent::booted();

        // Sinkronisasi nama ke akun mobile MPresensi setiap kali nama_karyawan diupdate
        static::updated(function (self $karyawan) {
            if ($karyawan->isDirty('nama_karyawan')) {
                if ($karyawan->presensiAccount()->exists()) {
                    $karyawan->presensiAccount()->update([
                        'name' => $karyawan->nama_karyawan,
                    ]);
                }
            }
        });
    }

    public function presensiAccount()
    {
        return $this->hasOne(MPresensi::class, 'karyawan_id');
    }
    
    public function department()
    {
        return $this->belongsTo(MDept::class, 'dept_id', 'dept_code');
    }
    
    public function division()
    {
        return $this->belongsTo(MDivision::class, 'div_id', 'div_code');
    }
    
    public function titleInfo()
    {
        return $this->belongsTo(MTitle::class, 'title', 'title_code');
    }

    public function levelInfo()
    {
        return $this->belongsTo(MLevel::class, 'level', 'level_code');
    }

    public function gradeInfo()
    {
        return $this->belongsTo(MGrade::class, 'grade', 'grade_code');
    }

    public function costSalesInfo()
    {
        return $this->belongsTo(MCostSales::class, 'cost_sales_id', 'cost_sales_code');
    }
}
