<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Presence extends Model
{
    use HasFactory;

    // Uses default connection (presensi_db)

    protected $fillable = [
        'user_id',
        'date',
        'clock_in',
        'clock_out',
        'latitude_in',
        'longitude_in',
        'latitude_out',
        'longitude_out',
        'status',
        'face_photo_in',
        'face_photo_out',
        'shift_code',
        'late_minutes',
        'overtime_minutes',
        'working_hours',
        'is_auto_clockout',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function user()
    {
        // Reference to MPresensi in master_db. 
        // Cross-database relations work on Eloquent level but not with strict foreign keys if on different servers.
        // Assuming same postgres instance, we can query it, but Eloquent needs 'setConnection' or explicit handling.
        // For simplicity, we just say it belongsTo MPresensi.
        return $this->setConnection('pgsql_master')->belongsTo(MPresensi::class, 'user_id');
    }

    public function presensiUser()
    {
        return $this->setConnection('pgsql_master')->belongsTo(MPresensi::class, 'user_id');
    }
    
    public function shiftCode()
    {
        return $this->belongsTo(ShiftCode::class, 'shift_code', 'code');
    }
    
    /**
     * Calculate working hours (handle overnight shifts correctly)
     */
    public function getWorkingHoursAttribute()
    {
        if (!$this->clock_in || !$this->clock_out) {
            return null;
        }
        
        try {
            // Parse times as Carbon instances on same date
            $baseDate = $this->date instanceof \Carbon\Carbon 
                ? $this->date->copy()->startOfDay()
                : \Carbon\Carbon::parse($this->date)->startOfDay();
            
            // Create time instances
            $clockInParts = explode(':', $this->clock_in);
            $clockOutParts = explode(':', $this->clock_out);
            
            $clockIn = $baseDate->copy()
                ->setTime((int)$clockInParts[0], (int)$clockInParts[1], (int)($clockInParts[2] ?? 0));
            
            $clockOut = $baseDate->copy()
                ->setTime((int)$clockOutParts[0], (int)$clockOutParts[1], (int)($clockOutParts[2] ?? 0));
            
            // If clock_out is earlier than clock_in, it means overnight shift
            if ($clockOut->lte($clockIn)) {
                $clockOut->addDay();
            }
            
            $diffInHours = $clockIn->diffInHours($clockOut, true);
            
            return round($diffInHours, 1);
        } catch (\Exception $e) {
            \Log::error('Error calculating working hours', [
                'presence_id' => $this->id,
                'clock_in' => $this->clock_in,
                'clock_out' => $this->clock_out,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
