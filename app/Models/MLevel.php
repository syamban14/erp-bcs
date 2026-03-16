<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MLevel extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_master';
    protected $table = 'm_level';
    public $timestamps = false; 

    protected $primaryKey = 'level_code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];

    // Relasi ke karyawan yang memiliki level ini
    public function karyawans()
    {
        return $this->hasMany(MKaryawan::class, 'level', 'level_code');
    }
}
