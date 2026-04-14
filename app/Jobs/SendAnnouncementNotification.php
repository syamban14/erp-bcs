<?php

namespace App\Jobs;

use App\Models\Announcement;
use App\Models\MPresensi;
use App\Services\FcmService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendAnnouncementNotification implements ShouldQueue
{
    use Queueable;

    public $announcement;

    /**
     * Create a new job instance.
     */
    public function __construct(Announcement $announcement)
    {
        $this->announcement = $announcement;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Jika ada target spesifik
            if ($this->announcement->target_user_id) {
                $user = MPresensi::find($this->announcement->target_user_id);
                if ($user && !empty($user->fcm_token)) {
                    $authorStr = ($this->announcement->author_name) ? "\n\nDari: " . $this->announcement->author_name . " (" . $this->announcement->author_division . ")" : "\n\nDari: Manajemen";
                    $cleanContent = strip_tags($this->announcement->content);

                    FcmService::sendNotification(
                        $user->fcm_token,
                        'Pengumuman Spesifik: ' . $this->announcement->title,
                        $cleanContent . $authorStr,
                        ['type' => 'announcement']
                    );
                }
                return;
            }

            // Jika global, broadcast ke semua user yang punya FCM token, di-chunk agar aman untuk memori
            MPresensi::whereNotNull('fcm_token')
                ->where('fcm_token', '!=', '')
                ->chunk(100, function ($users) {
                    $tokens = $users->pluck('fcm_token')->toArray();
                    
                    if (!empty($tokens)) {
                        $authorStr = ($this->announcement->author_name) ? "\n\nDari: " . $this->announcement->author_name . " (" . $this->announcement->author_division . ")" : "\n\nDari: Manajemen";
                        $cleanContent = strip_tags($this->announcement->content);

                        FcmService::sendNotification(
                            array_values(array_unique($tokens)),
                            'Pengumuman Baru: ' . $this->announcement->title,
                            $cleanContent . $authorStr,
                            ['type' => 'announcement']
                        );
                    }
                });

        } catch (\Exception $e) {
            Log::error('SendAnnouncementNotification Job Error: ' . $e->getMessage());
        }
    }
}
