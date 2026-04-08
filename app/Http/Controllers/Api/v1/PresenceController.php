<?php

namespace App\Http\Controllers\Api\v1;

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
            'type'              => 'required|in:in,out',
            'latitude'          => 'required',
            'longitude'         => 'required',
            // ✅ FOTO OPSIONAL — mobile developer tidak wajib mengirim foto.
            // Jika dikirim: harus berupa file gambar, maks 2MB.
            // Jika tidak dikirim: absensi tetap diproses normal tanpa foto.
            'photo'             => 'nullable|image|max:2048',
            // Anti-Fraud params
            'device_id'         => 'required|string',
            'verification_type' => 'nullable|in:pin,biometric',
            'pin'               => 'nullable|string|digits:6',
            'verification_data' => 'nullable|string',
        ]);

        $user = $request->user();
        
        // ✅ ANTI-FRAUD VALIDATION
        $fraudCheck = $this->validateAntiFraud($request, $user);
        if ($fraudCheck !== true) {
            return $fraudCheck; // Returns error response
        }

        $date = now()->format('Y-m-d');
        $time = now()->format('H:i:s');

        // Cari presence hari ini yang MASIH TERBUKA (belum clock-out)
        // PENTING: Tidak menggunakan ->whereNull('clock_out') pada level ini karena
        // shift malam (masuk 23:50 tn-1, keluar 07:00 tn-2) bisa punya clock_out di hari berbeda.
        // Yang kita cek: apakah ada sesi shift yang sedang aktif (belum selesai) untuk hari ini.
        $presence = Presence::where('user_id', $user->id)
            ->where('date', $date)
            ->whereNull('clock_out')  // Hanya yang masih TERBUKA
            ->first();

        if ($request->type === 'in') {
            return $this->clockIn($request, $user, $date, $time, $presence);
        } elseif ($request->type === 'out') {
            return $this->clockOut($request, $user, $date, $time);
        }
    }
    
    /**
     * Validate Device ID & PIN/Biometric
     */
    private function validateAntiFraud(Request $request, $user)
    {
        // Akun reviewer (Google Play/App Store) dikecualikan dari semua cek anti-fraud
        $bypassEmails = ['reviewer@tester.com'];
        if (in_array(strtolower($user->email ?? ''), $bypassEmails)) {
            return true;
        }

        // 1. Cek Device ID
        $registeredDevice = \App\Models\UserDevice::where('user_id', $user->id)
            ->where('device_id', $request->device_id)
            ->first();
            
        if (!$registeredDevice) {
            \Log::warning('Presence Fraud Attempt: Invalid Device ID', [
                'user_id' => $user->id,
                'sent_device_id' => $request->device_id
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Perangkat tidak dikenali. Silakan gunakan perangkat yang terdaftar.',
            ], 403);
        }
        
        // Update last active
        $registeredDevice->update(['last_active_at' => now()]);
        
        // 2. Verifikasi PIN (Jika metode verifikasi PIN)
        if ($request->verification_type === 'pin' || $request->has('pin')) {
            if (!$user->pin || !\Illuminate\Support\Facades\Hash::check($request->pin, $user->pin)) {
                 return response()->json([
                    'success' => false,
                    'message' => 'PIN salah.',
                ], 403);
            }
        }
        
        // 3. Verifikasi Biometrik (TODO: Logic signature payload belum dispesifikasikan, sementara trust registered device)
        if ($request->verification_type === 'biometric') {
            // Future implementation: Verify $request->verification_data with $registeredDevice->public_key
            // But we need to know WHAT was signed (payload).
        }
        
        return true;
    }

    
    private function clockIn($request, $user, $date, $time, $presence)
    {
        // Tolak jika shift hari ini masih TERBUKA (belum clock-out)
        if ($presence && $presence->clock_in) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi shift hari ini masih berjalan. Silakan clock-out terlebih dahulu.',
            ], 400);
        }

        // ── [SKENARIO 1] Blokir clock-in jika ada Cuti/Izin aktif hari ini ──
        // Cek tabel leaves
        $today = $date; // format Y-m-d
        $hasLeave = \App\Models\Leave::where('user_id', $user->id)
            ->whereIn('status', ['approved', 'pending'])
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->exists();

        if ($hasLeave) {
            return response()->json([
                'meta' => [
                    'code'    => 422,
                    'status'  => 'error',
                    'message' => 'Anda berstatus Cuti/Izin/Dinas untuk hari ini. Presensi masuk tidak dapat diproses.',
                ],
                'data' => null,
            ], 422);
        }

        // Cek tabel permission_requests
        $hasPermission = \App\Models\PermissionRequest::where('user_id', $user->id)
            ->whereIn('status', ['approved', 'pending'])
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->exists();

        if ($hasPermission) {
            return response()->json([
                'meta' => [
                    'code'    => 422,
                    'status'  => 'error',
                    'message' => 'Anda berstatus Cuti/Izin/Dinas untuk hari ini. Presensi masuk tidak dapat diproses.',
                ],
                'data' => null,
            ], 422);
        }
        
        $bypassEmails = ['reviewer@tester.com'];
        $user->load(['officeLocations', 'officeLocation']);
        if (!in_array(strtolower($user->email ?? ''), $bypassEmails)) {
            // Ambil semua lokasi — pivot dulu, fallback ke lokasi utama
            $locations = $user->officeLocations->isNotEmpty()
                ? $user->officeLocations
                : ($user->officeLocation ? collect([$user->officeLocation]) : collect());

            if ($locations->isNotEmpty()) {
                $geofencing = app(\App\Services\GeofencingService::class);
                $withinAny  = false;
                $closestMsg = null;
                $closestDist = PHP_INT_MAX;
                $closestOffice = null;

                foreach ($locations as $loc) {
                    $validation = $geofencing->validate(
                        $request->latitude,
                        $request->longitude,
                        $loc->latitude,
                        $loc->longitude,
                        $loc->radius
                    );
                    if ($validation['is_valid']) {
                        $withinAny = true;
                        break;
                    }
                    if ($validation['distance'] < $closestDist) {
                        $closestDist   = $validation['distance'];
                        $closestMsg    = $validation['message'];
                        $closestOffice = $loc->name;
                    }
                }

                if (!$withinAny) {
                    return response()->json([
                        'success' => false,
                        'message' => $closestMsg,
                        'data'    => [
                            'distance'    => $closestDist,
                            'office_name' => $closestOffice,
                        ]
                    ], 422);
                }
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
        
        // Foto opsional — hanya disimpan jika mobile mengirimkan file foto
        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('presences/in', 'public');
            $presence->face_photo_in = $path;
        }
        // Jika tidak ada foto, face_photo_in tetap null — absensi tetap valid
        
        
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
        // Cari presence yang belum clock out dalam 7 hari terakhir
        // (mencakup shift malam, lembur panjang, hingga karyawan yang lupa clock out beberapa hari)
        $sevenDaysAgo = now()->subDays(7)->format('Y-m-d');
        
        $presence = Presence::where('user_id', $user->id)
            ->where('date', '>=', $sevenDaysAgo)
            ->whereNotNull('clock_in')
            ->whereNull('clock_out')
            ->orderBy('date', 'desc') // ambil yang terbaru
            ->first();
        
        if (!$presence) {
            return response()->json(['message' => 'Anda belum clock in atau sudah clock out'], 400);
        }
        
        // Tidak ada batas waktu keras — karyawan boleh clock out kapanpun
        // Catatan: jika jam kerja anomali (>24jam), HR/admin bisa koreksi via AttendanceCorrection
        $dateString = is_string($presence->date) ? $presence->date : $presence->date->format('Y-m-d');
        $clockInDateTime = \Carbon\Carbon::parse($dateString . ' ' . $presence->clock_in);
        $now = now();
        $hoursElapsed = $now->diffInHours($clockInDateTime);
        
        \Log::info('Clock-out attempted', [
            'user_id'       => $user->id,
            'presence_date' => $dateString,
            'clock_in'      => $presence->clock_in,
            'hours_elapsed' => $hoursElapsed,
        ]);
        
        // ✅ GEOFENCING VALIDATION (Multi-Lokasi)
        $bypassEmails = ['reviewer@tester.com'];
        $user->load(['officeLocations', 'officeLocation']);
        if (!in_array(strtolower($user->email ?? ''), $bypassEmails)) {
            $locations = $user->officeLocations->isNotEmpty()
                ? $user->officeLocations
                : ($user->officeLocation ? collect([$user->officeLocation]) : collect());

            if ($locations->isNotEmpty()) {
                $geofencing = app(\App\Services\GeofencingService::class);
                $withinAny  = false;
                $closestMsg = null;
                $closestDist = PHP_INT_MAX;
                $closestOffice = null;

                foreach ($locations as $loc) {
                    $validation = $geofencing->validate(
                        $request->latitude,
                        $request->longitude,
                        $loc->latitude,
                        $loc->longitude,
                        $loc->radius
                    );
                    if ($validation['is_valid']) {
                        $withinAny = true;
                        break;
                    }
                    if ($validation['distance'] < $closestDist) {
                        $closestDist   = $validation['distance'];
                        $closestMsg    = $validation['message'];
                        $closestOffice = $loc->name;
                    }
                }

                if (!$withinAny) {
                    return response()->json([
                        'success' => false,
                        'message' => $closestMsg,
                        'data'    => [
                            'distance'    => $closestDist,
                            'office_name' => $closestOffice,
                        ]
                    ], 422);
                }
            }
        }

        $presence->clock_out = $time;
        $presence->latitude_out = $request->latitude;
        $presence->longitude_out = $request->longitude;

        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('presences/out', 'public');
            $presence->face_photo_out = $path;
        }
        
        // ✅ Hanya hitung working_hours (jam kerja aktual)
        // ❌ overtime_minutes TIDAK dihitung otomatis di sini.
        // Lembur WAJIB berdasarkan Surat Perintah Lembur (SPL) yang disetujui stakeholder.
        // Lihat: OvertimeRequest (table: overtime_requests, status: approved)
        if ($presence->shift_code) {
            $presence->working_hours = $this->shiftService->calculateWorkingHours(
                $presence->clock_in,
                $time,
                $presence->shift_code
            );
        }
        
        // overtime_minutes tetap 0 saat clock-out — hanya diisi by approved OvertimeRequest
        $presence->overtime_minutes = 0;

        $presence->save();
        
        // Load shift relation
        $presence->load('shiftCode');
        
        $response = [
            'success' => true,
            'message' => 'Clock out berhasil',
            'data' => new PresenceResource($presence),
        ];

        return response()->json($response);
    }
}
