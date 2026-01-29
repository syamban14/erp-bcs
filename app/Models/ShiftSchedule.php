<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShiftSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'shift_code', // Changed from shift_type
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function user()
    {
        // MPresensi ada di pgsql_master, tapi kita tidak perlu setConnection di sini
        // karena MPresensi model sudah set connection-nya sendiri
        return $this->belongsTo(MPresensi::class, 'user_id');
    }

    public function shiftCode()
    {
        return $this->belongsTo(ShiftCode::class, 'shift_code', 'code');
    }
}
