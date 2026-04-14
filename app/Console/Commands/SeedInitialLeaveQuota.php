<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MKaryawan;
use App\Models\LeaveBalance;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SeedInitialLeaveQuota extends Command
{
    protected $signature = 'leave:seed-initial-quota';

    protected $description = 'Mengisi kuota cuti awal (12 hari) untuk semua karyawan aktif yang sudah bekerja >= 1 tahun, untuk setiap tahun yang belum memiliki record (termasuk 2025 ke belakang).';

    public function handle()
    {
        $currentYear = Carbon::now()->year;
        $this->info("Memproses pengisian awal kuota cuti hingga tahun {$currentYear}...");

        $employees = MKaryawan::with('presensiAccount')
            ->where(function ($q) {
                $q->where('aktif', 'Y')
                  ->orWhere('aktif', '1')
                  ->orWhereNull('aktif');
            })
            ->whereNotNull('tgl_masuk')
            ->get();

        $created = 0;
        $skipped_tenure = 0;
        $skipped_exists = 0;
        $skipped_no_account = 0;

        foreach ($employees as $karyawan) {
            if (!$karyawan->presensiAccount) {
                $skipped_no_account++;
                continue;
            }

            // Parse tanggal masuk dengan dua format yang umum
            $joinDate = null;
            try {
                $raw = $karyawan->tgl_masuk;
                if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $raw)) {
                    $joinDate = Carbon::createFromFormat('d/m/Y', $raw);
                } elseif (preg_match('/^\d{4}-\d{2}-\d{2}/', $raw)) {
                    $joinDate = Carbon::parse($raw);
                }
            } catch (\Exception $e) {
                $joinDate = null;
            }

            // Lewati jika tanggal tidak valid atau total masa kerja < 1 tahun
            if (!$joinDate || Carbon::now()->diffInYears($joinDate) < 1) {
                $skipped_tenure++;
                continue;
            }

            $user = $karyawan->presensiAccount;

            // Hitung tahun pertama karyawan layak mendapat cuti
            // = tahun join + 1 (karena butuh 1 tahun kerja dulu)
            $firstEligibleYear = $joinDate->year + 1;

            // Loop dari tahun pertama layak hingga tahun sekarang
            for ($year = $firstEligibleYear; $year <= $currentYear; $year++) {

                // Periksa: sudah bekerja sejak tanggal anniversary di tahun ini?
                // (Misal: join Mar 2022 → tahun 2023 anniversary-nya Mar 2023, sdh terlewati)
                $anniversaryThisYear = $joinDate->copy()->year($year);
                if ($anniversaryThisYear->isFuture()) {
                    // Anniversary karyawan di tahun ini belum tiba
                    continue;
                }

                // Cek apakah sudah ada record untuk tahun ini
                $existing = LeaveBalance::where('user_id', $user->id)
                    ->where('year', $year)
                    ->exists();

                if ($existing) {
                    $skipped_exists++;
                    continue;
                }

                // Buat record baru untuk tahun tersebut
                LeaveBalance::create([
                    'user_id' => $user->id,
                    'year'    => $year,
                    'quota'   => 12,
                    'used'    => 0,
                ]);

                $created++;
                $this->line("  [CREATE] {$karyawan->nama_karyawan} → kuota 12 tahun {$year}");
            }
        }

        $msg = "Selesai. Dibuat: {$created} record | Sudah ada: {$skipped_exists} | Belum 1 tahun: {$skipped_tenure} | Tanpa akun: {$skipped_no_account}";
        $this->info($msg);
        Log::channel('daily')->info('SeedInitialLeaveQuota: ' . $msg);
    }
}
