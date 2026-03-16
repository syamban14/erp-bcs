<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\MPresensi;
use App\Models\UserDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BiometricController extends Controller
{
    /**
     * Register Biometric (Public Key) & Device ID
     */
    public function register(Request $request)
    {
        $request->validate([
            'public_key' => 'required|string',
            'device_id' => 'required|string',
            'device_name' => 'nullable|string',
        ]);

        $user = $request->user();

        // 1. Check if device_id is already registered to ANOTHER user (Anti-Fraud)
        // Note: user_id in user_devices is now foreign key to m_presensi
        $existingDevice = UserDevice::where('device_id', $request->device_id)
            ->where('user_id', '!=', $user->id)
            ->first();

        if ($existingDevice) {
            return response()->json([
                'meta' => [
                    'code' => 403,
                    'status' => 'error',
                    'message' => 'Device ID ini sudah terdaftar untuk pengguna lain. Hubungi IT jika ini kesalahan.',
                ],
                'data' => null
            ], 403);
        }

        // 2. Register/Update Device for current user
        $device = UserDevice::updateOrCreate(
            [
                'user_id' => $user->id,
                'device_id' => $request->device_id
            ],
            [
                'public_key' => $request->public_key,
                'device_name' => $request->device_name ?? 'Unknown Device',
                'last_active_at' => now(),
            ]
        );

        return response()->json([
            'meta' => [
                'code' => 200,
                'status' => 'success',
                'message' => 'Biometric registered successfully',
            ],
            'data' => $device
        ]);
    }

    /**
     * Biometric Login (Signature Verification)
     */
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|email',
            'signature' => 'required|string',
            'payload' => 'required|string',
            'device_id' => 'required|string',
        ]);

        // 1. Find User (MPresensi)
        $user = MPresensi::where('email', $request->username)->first();
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // 2. Find Device & Public Key
        $device = UserDevice::where('user_id', $user->id)
            ->where('device_id', $request->device_id)
            ->first();

        if (!$device) {
            return response()->json(['message' => 'Device not registered for this user'], 403);
        }

        // 3. Verify Signature
        // Assuming signature is Base64 encoded and Algorithm is SHA256
        $publicKey = $device->public_key;
        $signature = base64_decode($request->signature);
        $data = $request->payload;

        // Ensure public key is in PEM format
        if (!str_contains($publicKey, 'BEGIN PUBLIC KEY')) {
            $publicKey = "-----BEGIN PUBLIC KEY-----\n" . wordwrap($publicKey, 64, "\n", true) . "\n-----END PUBLIC KEY-----";
        }

        $verified = openssl_verify($data, $signature, $publicKey, OPENSSL_ALGO_SHA256);

        if ($verified !== 1) {
            return response()->json(['message' => 'Biometric verification failed (Invalid Signature)'], 401);
        }

        // 4. Login Success -> Issue Token
        $device->update(['last_active_at' => now()]);
        
        $token = $user->createToken('BiometricLogin-' . $device->device_name)->plainTextToken;

        return response()->json([
            'meta' => [
                'code' => 200,
                'status' => 'success',
                'message' => 'Login successful',
            ],
            'data' => [
                'token' => $token,
                'user' => $user,
            ]
        ]);
    }

    /**
     * Check Registration Status
     */
    public function status(Request $request)
    {
        $user = $request->user();

        // Check if has PIN
        $hasPin = !empty($user->pin);

        // Check if has any device registered (Biometric)
        // Adjust logic if we want to check for SPECIFIC device from header/param?
        // Requirement says: "is_biometric_registered: true" if user has registered ANY key?
        // Usually yes.
        $device = UserDevice::where('user_id', $user->id)->latest('last_active_at')->first();

        return response()->json([
            'meta' => [
                'code' => 200,
                'status' => 'success',
                'message' => 'OK',
            ],
            'data' => [
                'is_biometric_registered' => $device ? true : false,
                'is_pin_registered' => $hasPin,
                'registered_device_id' => $device ? $device->device_id : null,
            ]
        ]);
    }
}
