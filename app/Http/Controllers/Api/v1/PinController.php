<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\MPresensi;
use App\Models\UserDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PinController extends Controller
{
    /**
     * Register PIN
     */
    public function register(Request $request)
    {
        $request->validate([
            'pin' => 'required|string|digits:6',
        ]);

        $user = $request->user();

        $user->pin = Hash::make($request->pin);
        $user->save();

        return response()->json([
            'meta' => [
                'code'    => 200,
                'status'  => 'success',
                'message' => 'PIN registered successfully',
            ],
            'data' => null
        ]);
    }

    /**
     * Verify PIN (Login/Unlock) - Device Bound
     *
     * Logic:
     * 1. Cek PIN cocok dengan user
     * 2. [DEVICE BOUND] Jika user punya device terdaftar di user_devices,
     *    pastikan device_id request cocok. Jika tidak → 403 Forbidden.
     * 3. Jika belum ada device terdaftar → tetap lolos (user belum register biometric)
     */
    public function verify(Request $request)
    {
        $request->validate([
            'username'  => 'required|email',
            'pin'       => 'required|string|digits:6',
            'device_id' => 'required|string',
        ]);

        $user = MPresensi::where('email', $request->username)->first();

        // 1. Cek user & PIN
        if (!$user || !$user->pin || !Hash::check($request->pin, $user->pin)) {
            return response()->json([
                'meta' => [
                    'code'    => 401,
                    'status'  => 'error',
                    'message' => 'PIN salah atau belum terdaftar',
                ],
                'data' => null
            ], 401);
        }

        // 2. [DEVICE BOUND] Cek device terdaftar
        $registeredDevice = UserDevice::where('user_id', $user->id)->first();

        if ($registeredDevice) {
            if ($registeredDevice->device_id !== $request->device_id) {
                return response()->json([
                    'meta' => [
                        'code'    => 403,
                        'status'  => 'error',
                        'message' => 'Akses Ditolak: PIN hanya bisa digunakan di HP terdaftar. Silakan hubungi HRD untuk reset device.',
                    ],
                    'data' => null
                ], 403);
            }

            // Update last_active_at device
            $registeredDevice->update(['last_active_at' => now()]);
        }
        // Jika belum ada device terdaftar → tetap lolos

        // 3. Issue token jika belum login
        $token = null;
        if (!auth()->check()) {
            $token = $user->createToken('PinLogin-' . $request->device_id)->plainTextToken;
        }

        return response()->json([
            'meta' => [
                'code'    => 200,
                'status'  => 'success',
                'message' => 'Verifikasi PIN berhasil',
            ],
            'data' => [
                'token'        => $token,
                'user'         => $user,
                'device_bound' => $registeredDevice !== null,
            ]
        ]);
    }

    /**
     * Reset PIN milik user sendiri (via API, perlu auth)
     */
    public function resetPin(Request $request)
    {
        $user = $request->user();
        $user->pin = null;
        $user->save();

        return response()->json([
            'meta' => [
                'code'    => 200,
                'status'  => 'success',
                'message' => 'PIN berhasil direset',
            ],
            'data' => null
        ]);
    }
}
