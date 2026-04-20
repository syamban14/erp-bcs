<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SabbaticalLeave extends Model
{
    use HasFactory;

    protected $connection = 'pgsql'; 
    protected $table = 'sabbatical_leaves';

    protected $fillable = [
        'user_id',
        'quota',
        'used',
        'expires_at',
    ];

    protected $casts = [
        'quota' => 'integer',
        'used' => 'integer',
        'expires_at' => 'date',
    ];

    /**
     * Relasi ke User
     */
    public function user()
    {
        return $this->belongsTo(\App\Models\MPresensi::class, 'user_id');
    }

    /**
     * Sisa Kuota Cuti Besar
     */
    public function getRemainingQuota(): int
    {
        return max(0, $this->quota - $this->used);
    }

    /**
     * Cek apakah sisa kuota masih mencukupi
     */
    public function hasQuota(int $days): bool
    {
        return $this->getRemainingQuota() >= $days;
    }

    /**
     * Memotong saldo Cuti Besar
     */
    public function deductQuota(int $days): void
    {
        $this->increment('used', $days);
    }
    
    /**
     * Apakah cuti besar ini masih berlaku/aktif?
     */
    public function isValid(): bool
    {
        return \Carbon\Carbon::now()->startOfDay()->lte($this->expires_at);
    }
}
