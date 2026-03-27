<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Middleware: QueryTokenAuth
 *
 * Mengautentikasi user menggunakan Bearer Token yang dikirim
 * via query parameter `?token=` (bukan Authorization header).
 *
 * Digunakan khusus untuk endpoint export/download agar kompatibel
 * dengan Flutter url_launcher dan Android Download Manager.
 */
class QueryTokenAuth
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->query('token');

        if (!$token) {
            return response()->json([
                'meta' => ['code' => 401, 'status' => 'error', 'message' => 'Token tidak ditemukan.'],
                'data' => null,
            ], 401);
        }

        // Cari token di tabel personal_access_tokens
        $accessToken = PersonalAccessToken::findToken($token);

        if (!$accessToken) {
            return response()->json([
                'meta' => ['code' => 401, 'status' => 'error', 'message' => 'Token tidak valid atau sudah kadaluarsa.'],
                'data' => null,
            ], 401);
        }

        // Set authenticated user ke request
        $user = $accessToken->tokenable;

        if (!$user || !$user->is_active) {
            return response()->json([
                'meta' => ['code' => 401, 'status' => 'error', 'message' => 'Akun tidak aktif.'],
                'data' => null,
            ], 401);
        }

        // Inject user ke request seperti middleware auth:sanctum biasa
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
