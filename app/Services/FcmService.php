<?php

namespace App\Services;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmService
{
    /**
     * Mengirim notifikasi menggunakan FCM HTTP v1 API.
     *
     * @param string|array $fcmTokens Device token target (bisa multiple/array)
     * @param string $title Judul Notifikasi
     * @param string $body Isi Notifikasi
     * @param array $data Data tambahan (Hidden payload)
     * @return array|bool Status keberhasilan atau detail response
     */
    public static function sendNotification($fcmTokens, $title, $body, $data = [])
    {
        try {
            $tokens = is_array($fcmTokens) ? $fcmTokens : [$fcmTokens];
            
            // 1. Dapatkan Token OAuth 2.0 (Access Token 1 Jam) dari file Admin SDK
            $jsonKeyFilePath = storage_path('app/firebase/mybcs-firebase-adminsdk.json');
            
            if (!file_exists($jsonKeyFilePath)) {
                Log::error('FCM Service Error: File kredensial Firebase Admin SDK tidak ditemukan di ' . $jsonKeyFilePath);
                return false;
            }

            $credentials = new ServiceAccountCredentials(
                'https://www.googleapis.com/auth/firebase.messaging',
                json_decode(file_get_contents($jsonKeyFilePath), true)
            );
            
            $token = $credentials->fetchAuthToken(\Google\Auth\HttpHandler\HttpHandlerFactory::build());
            $accessToken = $token['access_token'];

            // 2. Baca informasi Project ID dari JSON Auth file
            $firebaseConfig = json_decode(file_get_contents($jsonKeyFilePath), true);
            $projectId = $firebaseConfig['project_id'] ?? 'bcs-proj';

            $apiUrl = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

            $successCount = 0;
            $responses = [];

            // 3. Tembakkan notifikasi per token ke endpoint FCM HTTP v1
            foreach ($tokens as $token) {
                if (empty($token)) {
                    continue;
                }

                $response = Http::withToken($accessToken)->post($apiUrl, [
                    'message' => [
                        'token' => $token,
                        'notification' => [
                            'title' => $title,
                            'body' => $body,
                        ],
                        'android' => [
                            'priority' => 'HIGH',
                            'notification' => [
                                'sound' => 'default',
                            ],
                        ],
                        'apns' => [
                            'payload' => [
                                'aps' => [
                                    'sound' => 'default',
                                ]
                            ]
                        ],
                        'data' => array_merge([
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        ], array_map('strval', $data)),
                    ]
                ]);

                if ($response->successful()) {
                    $successCount++;
                    Log::info("FCM Notification Terkirim ke token: " . substr($token, 0, 15) . '...', $response->json());
                } else {
                    Log::error("FCM Notification Gagal (Token: " . substr($token, 0, 15) . '...)', [
                        'status' => $response->status(),
                        'response' => $response->json()
                    ]);
                }

                $responses[] = $response->json();
            }

            return [
                'success' => $successCount > 0,
                'sent' => $successCount,
                'total' => count($tokens),
                'details' => $responses
            ];

        } catch (\Exception $e) {
            Log::error('FCM Service Exception: Gagal mengirim push notification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
}
