<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MAtasan extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_master';
    protected $table = 'm_atasan';
    public $timestamps = false; // Based on the SQL, there's create_date/modify_date, but not created_at/updated_at

    protected $guarded = [];

    // Relasi ke atasan (Karyawan)
    public function atasan()
    {
        return $this->belongsTo(MKaryawan::class, 'title_atasan', 'title');
    }

    // Relasi ke bawahan (Karyawan)
    public function bawahan()
    {
        return $this->belongsTo(MKaryawan::class, 'title_bawahan', 'title');
    }
}
