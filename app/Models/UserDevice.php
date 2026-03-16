<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserDevice extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_master';

    protected $fillable = [
        'user_id',
        'device_id',
        'device_name',
        'public_key',
        'last_active_at',
    ];

    protected $casts = [
        'last_active_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(MPresensi::class, 'user_id');
    }
}
