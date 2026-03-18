<?php

namespace App\Observers;

use App\Models\Loan;
use App\Models\Notification;
use App\Models\MPresensi;
use App\Services\FcmService;
use Illuminate\Support\Facades\Log;

class LoanObserver
{
    /**
     * Handle the Loan "created" event.
     * Mengirim notifikasi ke HR/Admin saat karyawan mengajukan kasbon.
     */
    public function created(Loan $loan): void
    {
        try {
            $userSubmitter = $loan->user;
            $namaPengaju = $userSubmitter ? $userSubmitter->name : 'Seseorang';
            $amountFormatted = 'Rp ' . number_format($loan->amount, 0, ',', '.');
            
            $title = "Pengajuan Kasbon Baru";
            $message = "{$namaPengaju} telah mengajukan kasbon sebesar {$amountFormatted}. Alasan: {$loan->reason}";

            // 1. Dapatkan daftar pengguna yang punya wewenang Approval (HR/Direktur/Superadmin)
            // Bisa disesuaikan dengan role Bapak. Contoh ini merujuk ke role HR & Direktur.
            $adminUsers = MPresensi::whereIn('role', ['hr', 'direktur', 'superadmin'])->get();

            foreach ($adminUsers as $admin) {
                // Buat Notifikasi In-App
                Notification::create([
                    'user_id' => $admin->id,
                    'title'   => $title,
                    'message' => $message,
                    'type'    => 'info',
                    'reference_id' => $loan->id,
                    'reference_type' => 'loan',
                ]);

                // Kirim FCM Push Notification
                if (!empty($admin->fcm_token)) {
                    FcmService::sendNotification(
                        $admin->fcm_token,
                        $title . " 💸",
                        $message,
                        ['type' => 'approval', 'id' => (string) $loan->id]
                    );
                }
            }
        } catch (\Exception $e) {
            Log::error('LoanObserver created notification failed: ' . $e->getMessage());
        }
    }

    /**
     * Handle the Loan "updated" event.
     * Memicu notifikasi FCM ke HP Pengaju setiap status Loan berubah (Approved/Rejected/Disburseddll).
     */
    public function updated(Loan $loan): void
    {
        // Pastikan kita HANYA menembakkan notif kalau Kolom STATUS berubah nilainya
        if ($loan->isDirty('status')) {
            try {
                $user = $loan->user;
                if (!$user) return; // Jika peminjam tidak ditemukan, keluar
                
                $title = "Update Kasbon Anda";
                $message = "";
                $type = 'info';
                $emoji = "📝";

                switch ($loan->status) {
                    case 'approved':
                        $title = "Kasbon Disetujui";
                        $message = "Pengajuan kasbon Anda telah disetujui. Harap menunggu pencairan.";
                        $type = 'success';
                        $emoji = "✅";
                        break;
                    
                    case 'active': // Ketika Admin klik 'Cairkan Dana'
                        $title = "Kasbon Telah Cair";
                        $message = "Kasbon Anda telah dicairkan ke rekening Anda.";
                        $type = 'success';
                        $emoji = "💸";
                        break;

                    case 'rejected':
                        $title = "Kasbon Ditolak";
                        $reason = $loan->rejection_reason ?? 'Tidak disertakan alasan.';
                        $message = "Pengajuan kasbon Anda terpaksa ditolak. Alasan: " . $reason;
                        $type = 'error';
                        $emoji = "❌";
                        break;
                        
                    case 'paid_off':
                        $title = "Kasbon Lunas!";
                        $message = "Selamat! Segala tunggakan cicilan kasbon Anda telah LUNAS.";
                        $type = 'success';
                        $emoji = "🎉";
                        break;

                    case 'cancelled':
                        $title = "Kasbon Dibatalkan";
                        $message = "Pengajuan kasbon Anda telah dibatalkan.";
                        $type = 'error';
                        break;
                }

                // Buat In-App Notification (Loncerng Kuning di Web jika ada)
                Notification::create([
                    'user_id' => $user->id,
                    'title'   => $title,
                    'message' => $message,
                    'type'    => $type,
                    'reference_id' => $loan->id,
                    'reference_type' => 'loan',
                ]);

                // Kirim Notifikasi Push ke Mobile App Flutter
                if (!empty($user->fcm_token)) {
                    FcmService::sendNotification(
                        $user->fcm_token,
                        "{$title} {$emoji}",
                        $message,
                        ['type' => 'loan']
                    );
                }

            } catch (\Exception $e) {
                Log::error('LoanObserver updated notification failed: ' . $e->getMessage());
            }
        }
    }
}
