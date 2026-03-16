<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Greeting extends Model
{
    protected $fillable = [
        'sender_user_id',
        'target_user_id',
        'announcement_id',
        'year',
    ];

    public function sender()
    {
        return $this->belongsTo(MPresensi::class, 'sender_user_id');
    }

    public function target()
    {
        return $this->belongsTo(MPresensi::class, 'target_user_id');
    }
}
