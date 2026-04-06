<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiErrorLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'method',
        'url',
        'status_code',
        'ip',
        'user_agent',
        'error_message',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(MPresensi::class, 'user_id');
    }
}
