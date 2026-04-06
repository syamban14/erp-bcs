<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Middleware untuk autentikasi export via query param ?token=
        $middleware->alias([
            'query.token' => \App\Http\Middleware\QueryTokenAuth::class,
        ]);
        
        // Catat error API 4xx/5xx ke api_error_logs
        $middleware->api(append: [
            \App\Http\Middleware\ApiErrorLogger::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Tangkap semua exception di rute API dan kembalikan sebagai JSON
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->wantsJson()) {

                // ValidationException (dari $request->validate()) → 422 + detail field
                if ($e instanceof \Illuminate\Validation\ValidationException) {
                    return response()->json([
                        'meta' => [
                            'code'    => 422,
                            'status'  => 'error',
                            'message' => 'Data tidak valid. Periksa kembali input Anda.',
                        ],
                        'errors' => $e->errors(),
                        'data'   => null,
                    ], 422);
                }

                // AuthenticationException (tidak login / token expired)
                if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                    return response()->json([
                        'meta' => [
                            'code'    => 401,
                            'status'  => 'error',
                            'message' => 'Sesi Anda telah berakhir. Silakan login kembali.',
                        ],
                        'data' => null,
                    ], 401);
                }

                // ModelNotFoundException (record tidak ditemukan)
                if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                    return response()->json([
                        'meta' => [
                            'code'    => 404,
                            'status'  => 'error',
                            'message' => 'Data yang diminta tidak ditemukan.',
                        ],
                        'data' => null,
                    ], 404);
                }

                // Exception umum lainnya (QueryException, Error, dll) → 500
                $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
                $message    = config('app.debug')
                    ? $e->getMessage()
                    : 'Terjadi kesalahan pada server. Silakan coba beberapa saat lagi.';

                return response()->json([
                    'meta' => [
                        'code'    => $statusCode,
                        'status'  => 'error',
                        'message' => $message,
                    ],
                    'data' => null,
                ], $statusCode >= 100 && $statusCode < 600 ? $statusCode : 500);
            }
        });
    })->create();
