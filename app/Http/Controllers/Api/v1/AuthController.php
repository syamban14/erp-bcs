<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Mail\ResetPasswordMail;
use App\Models\MPresensi;
use App\Models\UserDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // List email akun khusus yang dikecualikan dari device binding (contoh: reviewer store)
        $bypassDeviceEmails = [
            'reviewer@tester.com',
        ];

        $isReviewerAccount = in_array(strtolower($request->email), $bypassDeviceEmails);

        $request->validate([
            'email'        => 'required|email',
            'password'     => 'required',
            'device_id'    => $isReviewerAccount ? 'nullable|string' : 'required|string',
            'device_name'  => 'nullable|string',
            'device_token' => 'nullable|string',
        ]);

        // ── 1. Autentikasi Email + Password ──
        $user = MPresensi::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Kredensial yang diberikan salah.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Akun anda tidak aktif.'],
            ]);
        }

        // ── 2. Strict Device Binding (One User, One Device) ──
        // Akun reviewer (store review) dikecualikan dari pembatasan device ini.
        if (!$isReviewerAccount) {
            $registeredDevice = UserDevice::where('user_id', $user->id)->first();

            if ($registeredDevice) {
                // Device sudah terdaftar — harus cocok
                if ($registeredDevice->device_id !== $request->device_id) {
                    Log::warning('SECURITY [Strict Device Binding]: Login ditolak — device tidak cocok', [
                        'user_id'           => $user->id,
                        'user_email'        => $user->email,
                        'registered_device' => substr($registeredDevice->device_id, 0, 10) . '...',
                        'incoming_device'   => substr($request->device_id, 0, 10) . '...',
                        'ip'                => $request->ip(),
                    ]);

                    return response()->json([
                        'meta' => [
                            'code'    => 403,
                            'status'  => 'error',
                            'message' => 'Login ditolak: perangkat tidak dikenal',
                        ],
                        'data' => [
                            'error_code' => 'DEVICE_MISMATCH',
                            'message'    => 'Akun Anda terikat dengan HP lain. Silakan hubungi Admin untuk reset perangkat jika Anda ganti HP.',
                        ],
                    ], 403);
                }

                // Cocok — update last_active_at
                $registeredDevice->update(['last_active_at' => now()]);

            } else {
                // Belum ada device terdaftar — ini login pertama, register otomatis
                UserDevice::create([
                    'user_id'        => $user->id,
                    'device_id'      => $request->device_id,
                    'device_name'    => $request->device_name ?? 'Unknown Device',
                    'public_key'     => $request->public_key ?? 'NOT_PROVIDED',
                    'last_active_at' => now(),
                ]);

                Log::info('SECURITY [Device Registered]: Device baru terdaftar saat login', [
                    'user_id'     => $user->id,
                    'device_id'   => substr($request->device_id, 0, 10) . '...',
                    'device_name' => $request->device_name ?? 'Unknown Device',
                ]);
            }
        } else {
            Log::info('AUTH [Reviewer Bypass]: Login tanpa device binding', [
                'email' => $user->email,
                'ip'    => $request->ip(),
            ]);
        }

        // ── 3. Update FCM device token jika diberikan ──
        if ($request->device_token) {
            $user->update([
                'device_token' => $request->device_token,
                'fcm_token' => $request->device_token
            ]);
        }

        // ── 4. Issue Sanctum token ──
        $token = $user->createToken('mobile-app')->plainTextToken;

        // ── 5. Load office location untuk geofencing ──
        $user->load('officeLocation');

        $officeData = null;
        if ($user->officeLocation) {
            $officeData = [
                'office_lat'    => $user->officeLocation->latitude,
                'office_lng'    => $user->officeLocation->longitude,
                'office_radius' => $user->officeLocation->radius,
                'office_name'   => $user->officeLocation->name,
            ];
        }

        return response()->json([
            'meta' => [
                'code'    => 200,
                'status'  => 'success',
                'message' => 'Login berhasil',
            ],
            'data' => array_merge(
                [
                    'token'         => $token,
                    'device_bound'  => true,  // Konfirmasi ke mobile: device binding aktif
                    'user'          => $user->load('karyawan')->toArray(),
                ],
                $officeData ? ['office' => $officeData] : []
            ),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout berhasil']);
    }

    /**
     * Forgot Password
     * POST /api/forgot-password
     *
     * Mencari user berdasarkan email atau NIK (no_karyawan),
     * generate password acak, update database, dan kirim email.
     */
    public function forgotPassword(Request $request)
    {
        // ── 1. Validasi Input ──
        $request->validate([
            'email' => 'required|string',
        ]);

        $input = trim($request->email);

        // ── 2. Cari User di tabel m_presensi ──
        // Support: email saja
        $user = MPresensi::where('email', $input)->first();

        if (! $user) {
            return response()->json([
                'message' => 'Email atau ID Karyawan tidak terdaftar di dalam sistem.',
                'errors'  => [
                    'email' => ['Data tidak ditemukan.'],
                ],
            ], 404);
        }

        if (! $user->email) {
            return response()->json([
                'message' => 'Akun Anda tidak memiliki email terdaftar. Hubungi Admin.',
                'errors'  => [
                    'email' => ['Email tidak tersedia pada akun ini.'],
                ],
            ], 422);
        }

        // ── 3. Generate Password Acak (8 karakter alphanumeric) ──
        $newPassword = Str::random(8);

        // ── 4. Simpan Password Terenkripsi ke Database ──
        $user->password = Hash::make($newPassword);
        $user->save();

        Log::info('[ForgotPassword] Password direset', [
            'user_id' => $user->id,
            'email'   => $user->email,
            'ip'      => $request->ip(),
        ]);

        // ── 5. Kirim Email dengan Password Baru ──
        try {
            Mail::to($user->email)->send(new ResetPasswordMail($newPassword, $user->name ?? ''));
        } catch (\Exception $e) {
            Log::error('[ForgotPassword] Gagal kirim email', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Password berhasil direset, namun gagal mengirim email. Hubungi Admin.',
                'status'  => 'partial_success',
            ], 500);
        }

        // ── 6. Response Sukses ──
        return response()->json([
            'status'  => 'success',
            'message' => 'Detail login baru telah dibuat dan dikirimkan ke email terdaftar Anda.',
        ], 200);
    }
}
