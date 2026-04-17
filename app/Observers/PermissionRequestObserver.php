<?php

namespace App\Observers;

use App\Models\PermissionRequest;
use App\Models\Notification;

class PermissionRequestObserver
{
    /**
     * Handle the PermissionRequest "updated" event.
     */
    public function updated(PermissionRequest $permission): void
    {
        // Only trigger when status changes from pending
        if ($permission->isDirty('status') && $permission->status !== 'pending') {
            
            $title = "Pengajuan Izin " . ucfirst($permission->status);
            
            $type = match($permission->status) {
                'approved' => 'success',
                'rejected' => 'error',
                default => 'info'
            };
            
            $message = "Pengajuan izin " . $permission->type . 
                       " telah " . strtoupper($permission->status) . ".";

            Notification::create([
                'user_id' => $permission->user_id,
                'title'   => $title,
                'message' => $message,
                'type'    => $type,
                'reference_id' => $permission->id,
                'reference_type' => 'permission',
            ]);

            // Auto clock-out trigger
            if ($permission->status === 'approved' && str_contains(strtolower($permission->type), 'pulang awal')) {
                $this->autoClockOut($permission);
            }
        }
    }

    /**
     * Otomatis clock-out karyawan bila izin pulang awal disetujui.
     */
    private function autoClockOut(PermissionRequest $permission): void
    {
        $userId    = $permission->user_id;
        $date      = $permission->start_date?->format('Y-m-d'); // menggunakan start_date
        $leaveTime = $permission->time; // jam yang diajukan (cth: '14:00' atau '14:00:00')

        if (!$date || !$leaveTime) {
            \Illuminate\Support\Facades\Log::warning("Auto clock-out skipped for user {$userId}: date or time is missing.");
            return;
        }

        // Cek apakah ada record presensi hari ini
        $presence = \App\Models\Presence::where('user_id', $userId)
            ->whereDate('date', $date)
            ->first();

        if (!$presence) {
            \Illuminate\Support\Facades\Log::warning("Auto clock-out skipped for user {$userId}: no clock-in record found today.");
            return;
        }

        if ($presence->clock_out) {
            \Illuminate\Support\Facades\Log::info("Auto clock-out skipped for user {$userId}: already clocked out.");
            return;
        }

        // Format waktu minimal H:i:s
        if (strlen($leaveTime) === 5) {
            $leaveTime .= ':00';
        }

        // Jalankan clock-out
        $presence->update([
            'clock_out'        => $leaveTime,
            'status'           => 'Hadir (Izin Pulang Awal)',
            'latitude_out'     => null,
            'longitude_out'    => null,
            'is_auto_clockout' => true,
        ]);

        \Illuminate\Support\Facades\Log::info("Auto clock-out berhasil untuk user {$userId} jam {$leaveTime}");

        // Temukan user untuk Notifikasi FCM
        $user = \App\Models\MPresensi::find($userId);
        if ($user) {
            $this->sendAutoClockoutNotification($user, $leaveTime);
        }
    }

    /**
     * Kirim push notification ke Firebase
     */
    private function sendAutoClockoutNotification(\App\Models\MPresensi $user, string $leaveTime): void
    {
        $fcmToken = $user->fcm_token;
        if (!$fcmToken) return;

        $title = 'Clock-Out Otomatis ✅';
        $body  = "Izin pulang awal Anda telah disetujui. Clock-out tercatat pukul {$leaveTime}.";

        // Menggunakan FcmService apabila sudah di-upgrade ke v1 dan ada integrasi helper yang serupa,
        // namun instruksi backend memandu kita untuk bisa langsung tembak FCM v1 API di sini.
        try {
            $jsonKeyFilePath = storage_path('app/firebase/mybcs-firebase-adminsdk.json');
            if (!file_exists($jsonKeyFilePath)) return;

            $credentials = new \Google\Auth\Credentials\ServiceAccountCredentials(
                'https://www.googleapis.com/auth/firebase.messaging',
                json_decode(file_get_contents($jsonKeyFilePath), true)
            );
            $token = $credentials->fetchAuthToken(\Google\Auth\HttpHandler\HttpHandlerFactory::build());
            $accessToken = $token['access_token'];
            
            $firebaseConfig = json_decode(file_get_contents($jsonKeyFilePath), true);
            $projectId = $firebaseConfig['project_id'] ?? 'bcs-proj';

            $payload = [
                'message' => [
                    'token' => $fcmToken,
                    'notification' => [
                        'title' => $title,
                        'body'  => $body,
                    ],
                    'android' => [
                        'priority' => 'high',
                        'notification' => [
                            'channel_id' => 'high_importance_channel',
                            'sound'      => 'default',
                        ],
                    ],
                    'apns' => [
                        'headers' => [
                            'apns-priority'  => '10',
                            'apns-push-type' => 'alert',
                        ],
                        'payload' => [
                            'aps' => [
                                'alert' => [
                                    'title' => $title,
                                    'body'  => $body,
                                ],
                                'sound'             => 'default',
                                'badge'             => 1,
                                'content-available' => 1,
                                'mutable-content'   => 1,
                            ],
                        ],
                    ],
                    // Semua target di mapping ke raw strval karena Firebase mensyaratkan tipe String
                    'data' => [
                        'type'      => 'auto_clockout',
                        'clock_out' => (string)$leaveTime,
                        'date'      => (string)now()->toDateString(),
                    ],
                ],
            ];

            \Illuminate\Support\Facades\Http::withToken($accessToken)
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", $payload);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Firebase Auto-Clockout FCM Error: ' . $e->getMessage());
        }
    }
}
