<?php

namespace Database\Seeders;

use App\Models\MPresensi;
use App\Models\UserDevice;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

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

        $reviewerEmail    = 'reviewer@tester.com';
        $reviewerPassword = 'Reviewer123!';
        $reviewerPin      = '123456';

        // 1. Buat atau perbarui record di m_presensi
        //    karyawan_id = null karena ini bukan karyawan nyata
        $user = MPresensi::updateOrCreate(
            ['email' => $reviewerEmail],
            [
                'karyawan_id'     => null,
                'name'            => 'Google Reviewer',
                'email'           => $reviewerEmail,
                'password'        => Hash::make($reviewerPassword),
                'pin'             => Hash::make($reviewerPin),
                'role'            => 'karyawan',
                'employment_type' => 'regular',
                'is_active'       => true,
                'phone'           => null,
                'photo'           => null,
                'fcm_token'       => null,
                'device_token'    => null,
            ]
        );

        $this->command->info("✅ m_presensi: ID={$user->id}, Email={$user->email}");

        // 2. Bersihkan Device Binding agar reviewer bisa login dari device mana saja
        $deleted = UserDevice::where('user_id', $user->id)->delete();
        if ($deleted > 0) {
            $this->command->warn("🔄 Device binding dibersihkan ({$deleted} record).");
        }

        // 3. Bersihkan semua token Sanctum lama
        $user->tokens()->delete();

        // 4. Tampilkan ringkasan
        $this->command->info('');
        $this->command->info('📋 RINGKASAN AKUN REVIEWER:');
        $this->command->table(
            ['Field', 'Value'],
            [
                ['Nama',         'Google Reviewer'],
                ['Email Login',  $reviewerEmail],
                ['Password',     $reviewerPassword],
                ['PIN Aplikasi', $reviewerPin],
                ['Role',         'karyawan'],
                ['Status',       'AKTIF ✅'],
                ['Device Bound', 'Tidak (bisa login dari device baru)'],
            ]
        );
        $this->command->info('==========================================================');
        $this->command->info('  Selesai! Akun Reviewer siap digunakan.');
        $this->command->info('==========================================================');
    }
}
