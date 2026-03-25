<?php

namespace Database\Seeders;

use App\Models\MKaryawan;
use App\Models\MPresensi;
use App\Models\UserDevice;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class ReviewerAccountSeeder extends Seeder
{
    /**
     * Akun Dummy untuk keperluan review Google Play & Apple App Store.
     *
     * Jalankan dengan:
     *   php artisan db:seed --class=ReviewerAccountSeeder
     *
     * CATATAN: Akun ini harus AKTIF TERUS dan TIDAK DIBLOKIR.
     */
    public function run(): void
    {
        $this->command->info('==========================================================');
        $this->command->info('  Membuat Akun Reviewer (Google Play / App Store)...');
        $this->command->info('==========================================================');

        // ─────────────────────────────────────────────
        // Konfigurasi Akun Reviewer
        // ─────────────────────────────────────────────
        $reviewerNip  = 'ID-9999';
        $reviewerEmail = 'reviewer@bcsgroup.com';
        $reviewerPassword = 'Reviewer123!';
        $reviewerPin  = '123456';

        // ─────────────────────────────────────────────
        // 1. Buat atau perbarui record MKaryawan (m_karyawan)
        // ─────────────────────────────────────────────
        $karyawan = MKaryawan::updateOrCreate(
            // Kondisi pencarian (cari berdasarkan NIP agar tidak duplikat)
            ['nip' => $reviewerNip],
            // Data yang akan diisi / di-update
            [
                'nip'           => $reviewerNip,
                'nama_karyawan' => 'Google Reviewer',
                'email'         => $reviewerEmail,
                'dept_id'       => null, // Tidak masuk departemen manapun
                'div_id'        => null,
                'title'         => null,
                'level'         => null,
                'grade'         => null,
                'status'        => 'active',
            ]
        );

        $this->command->info("✅ m_karyawan: ID={$karyawan->id}, NIP={$karyawan->nip}");

        // ─────────────────────────────────────────────
        // 2. Buat atau perbarui record MPresensi (m_presensi)
        // ─────────────────────────────────────────────
        $user = MPresensi::updateOrCreate(
            // Kondisi pencarian
            ['email' => $reviewerEmail],
            // Data yang akan diisi / di-update
            [
                'karyawan_id'     => $karyawan->id,
                'name'            => 'Google Reviewer',
                'email'           => $reviewerEmail,
                'password'        => Hash::make($reviewerPassword),
                'pin'             => Hash::make($reviewerPin),
                'role'            => 'karyawan',
                'employment_type' => 'permanent',
                'is_active'       => true,
                'phone'           => null,
                'photo'           => null,
                'fcm_token'       => null,
                'device_token'    => null,
            ]
        );

        $this->command->info("✅ m_presensi: ID={$user->id}, Email={$user->email}");

        // ─────────────────────────────────────────────
        // 3. Bersihkan Device Binding sehingga reviewer
        //    bisa login dari device mana saja (first login)
        // ─────────────────────────────────────────────
        $deleted = UserDevice::where('user_id', $user->id)->delete();
        if ($deleted > 0) {
            $this->command->warn("🔄 Device binding untuk akun reviewer telah dibersihkan ({$deleted} record).");
        }

        // ─────────────────────────────────────────────
        // 4. Bersihkan semua Sanctum token lama
        // ─────────────────────────────────────────────
        $user->tokens()->delete();

        // ─────────────────────────────────────────────
        // 5. Tampilkan ringkasan
        // ─────────────────────────────────────────────
        $this->command->info('');
        $this->command->info('📋 RINGKASAN AKUN REVIEWER:');
        $this->command->table(
            ['Field', 'Value'],
            [
                ['Nama',          'Google Reviewer'],
                ['NIP / ID',      $reviewerNip],
                ['Email Login',   $reviewerEmail],
                ['Password',      $reviewerPassword],
                ['PIN Aplikasi',  $reviewerPin],
                ['Role',          'karyawan'],
                ['Status',        'AKTIF ✅'],
                ['Device Bound',  'Tidak (bisa login dari device baru)'],
            ]
        );
        $this->command->info('==========================================================');
        $this->command->info('  Selesai! Akun Reviewer siap digunakan.');
        $this->command->info('==========================================================');

        Log::info('ReviewerAccountSeeder: Akun reviewer berhasil dibuat/diperbarui.', [
            'karyawan_id' => $karyawan->id,
            'user_id'     => $user->id,
            'email'       => $reviewerEmail,
        ]);
    }
}
