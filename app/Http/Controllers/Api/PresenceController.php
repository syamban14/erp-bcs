<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Presence;
use App\Http\Resources\PresenceResource;
use App\Services\ShiftService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PresenceController extends Controller
{
    protected $shiftService;
    
    public function __construct(ShiftService $shiftService)
    {
        $this->shiftService = $shiftService;
    }
    
    /**
     * Get presence history (with optional date filter)
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $query = Presence::where('user_id', $user->id);
        
        // Filter by date if provided
        if ($request->has('date')) {
            $query->where('date', $request->date);
            
            // Return single record if date filter is used
            $presence = $query->first();
            
            if (!$presence) {
                return response()->json([
                    'message' => 'No attendance record found for this date',
                    'data' => null
                ], 404);
            }
            
            return response()->json([
                'message' => 'Success',
                'data' => [
                    'id' => $presence->id,
                    'date' => $presence->date,
                    'clock_in' => $presence->clock_in,
                    'clock_out' => $presence->clock_out,
                    'status' => $presence->late_minutes > 0 ? 'Terlambat' : 'Tepat Waktu',
                    'latitude_in' => $presence->latitude_in,
                    'longitude_in' => $presence->longitude_in,
                ]
            ]);
        }
        
        // Return paginated list if no date filter
        $presences = $query->orderBy('date', 'desc')
            ->paginate(10);
        
        return PresenceResource::collection($presences);
    }

    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:in,out',
            'latitude' => 'required',
            'longitude' => 'required',
            'photo' => 'nullable|image|max:2048',
        ]);

        $user = $request->user();
        $date = now()->format('Y-m-d');
        $time = now()->format('H:i:s');

        // Check existing presence for today
        $presence = Presence::where('user_id', $user->id)->where('date', $date)->first();

        if ($request->type === 'in') {
            return $this->clockIn($request, $user, $date, $time, $presence);
        } elseif ($request->type === 'out') {
            return $this->clockOut($request, $user, $date, $time);
        }
    }
    
    private function clockIn($request, $user, $date, $time, $presence)
    {
        if ($presence && $presence->clock_in) {
            return response()->json(['message' => 'Anda sudah melakukan clock in hari ini'], 400);
        }
        
        // ✅ GEOFENCING VALIDATION
        $user->load('officeLocation');
        if ($user->officeLocation) {
            $geofencing = app(\App\Services\GeofencingService::class);
            
            $validation = $geofencing->validate(
                $request->latitude,
                $request->longitude,
                $user->officeLocation->latitude,
                $user->officeLocation->longitude,
                $user->officeLocation->radius
            );
            
            if (!$validation['is_valid']) {
                return response()->json([
                    'success' => false,
                    'message' => $validation['message'],
                    'data' => [
                        'distance' => $validation['distance'],
                        'max_radius' => $validation['radius'],
                        'office_name' => $user->officeLocation->name,
                    ]
                ], 422);
            }
        }
        
        // Check if user is shift employee (has any shift schedule)
        $hasAnyShiftSchedule = \App\Models\ShiftSchedule::where('user_id', $user->id)->exists();
        
        // Get shift schedule untuk hari ini
        $shift = $this->shiftService->getMyShiftToday($user->id);
        
        // ✅ VALIDASI UNTUK KARYAWAN SHIFT
        if ($hasAnyShiftSchedule) {
            // Karyawan shift WAJIB punya jadwal hari ini
            if (!$shift) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki jadwal shift hari ini. Silakan hubungi SPV/Manager Anda.',
                ], 400);
            }
            
            // Validasi jika hari ini off/libur
            if (isset($shift['is_off']) && $shift['is_off']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hari ini jadwal libur. Tidak bisa clock in.',
                ], 400);
            }
            
            // Validasi waktu clock-in: minimal 1 jam sebelum jam masuk
            if (isset($shift['time_in'])) {
                $shiftStartTime = \Carbon\Carbon::parse($shift['time_in']);
                $currentTime = \Carbon\Carbon::now();
                $earliestClockIn = $shiftStartTime->copy()->subHour(); // 1 jam sebelum
                
                \Log::info('Clock-in time validation', [
                    'user_id' => $user->id,
                    'current_time' => $currentTime->format('H:i:s'),
                    'shift_start' => $shiftStartTime->format('H:i:s'),
                    'earliest_allowed' => $earliestClockIn->format('H:i:s'),
                    'is_too_early' => $currentTime->lt($earliestClockIn)
                ]);
                
                if ($currentTime->lt($earliestClockIn)) {
                    $allowedTime = $earliestClockIn->format('H:i');
                    $currentTimeStr = $currentTime->format('H:i');
                    $shiftName = $shift['shift_name'] ?? 'Shift';
                    
                    return response()->json([
                        'success' => false,
                        'message' => "Belum waktunya clock in. Shift {$shiftName} dimulai pukul {$shiftStartTime->format('H:i')}. Anda bisa clock in mulai pukul {$allowedTime}. Waktu sekarang: {$currentTimeStr}.",
                    ], 400);
                }
            }
        }
        // Karyawan regular: flexible, no shift required

        if (!$presence) {
            $presence = new Presence();
            $presence->user_id = $user->id;
            $presence->date = $date;
        }

        $presence->clock_in = $time;
        $presence->latitude_in = $request->latitude;
        $presence->longitude_in = $request->longitude;
        
        
        // Simpan shift code dan hitung late minutes
        if ($shift) {
            // Karyawan shift: gunakan jam shift
            $presence->shift_code = $shift['shift_code'];
            $scheduledTimeIn = $shift['time_in'] . ':00';
            
            \Log::info('SHIFT EMPLOYEE - Calculating late minutes', [
                'user_id' => $user->id,
                'actual_time' => $time,
                'scheduled_time' => $scheduledTimeIn,
                'shift_code' => $shift['shift_code']
            ]);
            
            $presence->late_minutes = (int) $this->shiftService->calculateLateMinutes($time, $scheduledTimeIn);
        } else {
            // Karyawan regular: gunakan jam standar 08:00
            $standardTimeIn = '08:00:00';
            
            \Log::info('REGULAR EMPLOYEE - Calculating late minutes', [
                'user_id' => $user->id,
                'actual_time' => $time,
                'scheduled_time' => $standardTimeIn
            ]);
            
            $presence->late_minutes = (int) $this->shiftService->calculateLateMinutes($time, $standardTimeIn);
        }
        
        \Log::info('Late minutes calculated', [
            'user_id' => $user->id,
            'late_minutes' => $presence->late_minutes
        ]);
        
        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('presences/in', 'public');
            $presence->face_photo_in = $path;
        }
        
        
        // Set status berdasarkan keterlambatan
        $presence->status = $presence->late_minutes > 0 ? 'Terlambat' : 'Tepat Waktu';
        $presence->save();
        
        // Load shift relation
        $presence->load('shiftCode');
        
        $response = [
            'success' => true,
            'message' => 'Clock in berhasil',
            'data' => new PresenceResource($presence),
        ];
        
        // Tambah warning jika late
        if ($presence->late_minutes > 0) {
            $response['warning'] = "Anda terlambat {$presence->late_minutes} menit";
        }

        return response()->json($response);
    }
    
    private function clockOut($request, $user, $date, $time)
    {
        // Untuk shift malam yang melewati tengah malam, cari presence yang belum clock out
        // dari hari ini ATAU kemarin (dalam 24 jam terakhir)
        $yesterday = now()->subDay()->format('Y-m-d');
        
        $presence = Presence::where('user_id', $user->id)
            ->whereIn('date', [$date, $yesterday])
            ->whereNotNull('clock_in')
            ->whereNull('clock_out')
            ->orderBy('date', 'desc')
            ->first();
        
        if (!$presence) {
            return response()->json(['message' => 'Anda belum clock in atau sudah clock out'], 400);
        }
        
        // Validasi: clock out maksimal 24 jam dari clock in
        $dateString = is_string($presence->date) ? $presence->date : $presence->date->format('Y-m-d');
        $clockInDateTime = \Carbon\Carbon::parse($dateString . ' ' . $presence->clock_in);
        $now = now();
        
        if ($now->diffInHours($clockInDateTime) > 24) {
            return response()->json(['message' => 'Clock out melebihi batas waktu (24 jam dari clock in)'], 400);
        }
        
        // ✅ GEOFENCING VALIDATION
        $user->load('officeLocation');
        if ($user->officeLocation) {
            $geofencing = app(\App\Services\GeofencingService::class);
            
            $validation = $geofencing->validate(
                $request->latitude,
                $request->longitude,
                $user->officeLocation->latitude,
                $user->officeLocation->longitude,
                $user->officeLocation->radius
            );
            
            if (!$validation['is_valid']) {
                return response()->json([
                    'success' => false,
                    'message' => $validation['message'],
                    'data' => [
                        'distance' => $validation['distance'],
                        'max_radius' => $validation['radius'],
                        'office_name' => $user->officeLocation->name,
                    ]
                ], 422);
            }
        }

        $presence->clock_out = $time;
        $presence->latitude_out = $request->latitude;
        $presence->longitude_out = $request->longitude;

        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('presences/out', 'public');
            $presence->face_photo_out = $path;
        }
        
        // Calculate overtime and working hours
        if ($presence->shift_code) {
            $shift = $this->shiftService->getMyShiftToday($user->id);
            if ($shift) {
                $scheduledTimeOut = $shift['time_out'] . ':00';
                $presence->overtime_minutes = $this->shiftService->calculateOvertimeMinutes($time, $scheduledTimeOut);
            }
            
            $presence->working_hours = $this->shiftService->calculateWorkingHours(
                $presence->clock_in,
                $time,
                $presence->shift_code
            );
        }

        $presence->save();
        
        // Load shift relation
        $presence->load('shiftCode');
        
        $response = [
            'success' => true,
            'message' => 'Clock out berhasil',
            'data' => new PresenceResource($presence),
        ];
        
        // Tambah info overtime
        if ($presence->overtime_minutes > 0) {
            $response['info'] = "Lembur {$presence->overtime_minutes} menit";
        }

        return response()->json($response);
    }
}
