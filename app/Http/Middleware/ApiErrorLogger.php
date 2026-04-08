<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\ApiErrorLog;
use Illuminate\Support\Facades\Log;

class ApiErrorLogger
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Log semua request yang masuk ke endpoint API (Baik sukses maupun error)
        if ($request->is('api/*')) {
            try {
                    // Coba ambil user_id jika ada
                    $userId = optional($request->user('sanctum'))->id ?? optional($request->user())->id;
                    
                    $payload = $request->except(['password', 'password_confirmation', 'pin']);
                    
                    $errorMessage = $response->getContent();
                    // Jika json, ambil pesannya saja atau keseluruhan kalau bukan json
                    $decoded = json_decode($errorMessage, true);
                    if (json_last_error() === JSON_ERROR_NONE && isset($decoded['message'])) {
                        $errorMessage = $decoded['message'];
                        if (isset($decoded['errors'])) {
                            $errorMessage .= ' | ' . json_encode($decoded['errors']);
                        }
                    }

                    ApiErrorLog::create([
                        'user_id' => $userId,
                        'method' => $request->method(),
                        'url' => $request->fullUrl(),
                        'status_code' => $response->getStatusCode(),
                        'ip' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'error_message' => substr(strip_tags((string)$errorMessage), 0, 5000), // Max 5000 chars
                        'payload' => $payload,
                    ]);
                } catch (\Exception $e) {
                    // Fallback to normal log if database failure occurs
                    Log::error('ApiErrorLogger failed to save: ' . $e->getMessage());
                }
        }

        return $response;
    }
}
