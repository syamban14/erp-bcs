<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShiftCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'time_in',
        'time_out',
        'is_off',
    ];

    protected $casts = [
        'is_off' => 'boolean',
    ];

    public function schedules()
    {
        return $this->hasMany(ShiftSchedule::class, 'shift_code', 'code');
    }
}
