<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MPresensi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_token' => 'nullable|string',
        ]);

        // Check in master_db.m_presensi
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

        // Update device token if provided
        if ($request->device_token) {
            $user->update(['device_token' => $request->device_token]);
        }

        // Issue token
        // Note: Sanctum tables (personal_access_tokens) are usually in the default DB (presensi_db).
        // If MPresensi model connection is pgsql_master, $user->createToken() might try to save to master_db if not configured otherwise.
        // Standard Sanctum uses the default connection for PATs. 
        // We need to ensure PersonalAccessToken model uses default connection.
        // Assuming default config, it stores in presensi_db.
        
        $token = $user->createToken('mobile-app')->plainTextToken;

        // Load office location for geofencing
        $user->load('officeLocation');
        
        $officeData = null;
        if ($user->officeLocation) {
            $officeData = [
                'office_lat' => $user->officeLocation->latitude,
                'office_lng' => $user->officeLocation->longitude,
                'office_radius' => $user->officeLocation->radius,
                'office_name' => $user->officeLocation->name,
            ];
        }

        return response()->json([
            'message' => 'Login berhasil',
            'token' => $token,
            'user' => array_merge(
                $user->load('karyawan')->toArray(),
                $officeData ?? []
            ),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout berhasil']);
    }
}
