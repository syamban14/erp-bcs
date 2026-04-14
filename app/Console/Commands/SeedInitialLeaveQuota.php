<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MPresensi;
use App\Models\MKaryawan;
use App\Models\LeaveBalance;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SeedInitialLeaveQuota extends Command
{
    protected $signature = 'leave:seed-initial-quota';

    protected $description = 'Mengisi kuota cuti awal (12 hari) untuk semua user mobile yang menggunakan koneksi ke karyawan dan sudah bekerja >= 1 tahun, untuk setiap tahun yang belum ada recordnya.';

    /**
     * Parse tgl_masuk dari berbagai kemungkinan format tanggal di database legacy.
     */
    private function parseJoinDate(?string $raw): ?Carbon
    {
        if (empty($raw) || $raw === '0000-00-00' || $raw === '00/00/0000') {
            return null;
        }

        $formats = [
            'd/m/Y',        // 15/03/2022
            'd/n/Y',        // 15/3/2022 (bulan tanpa nol)
            'Y-m-d',        // 2022-03-15
            'Y-m-d H:i:s',  // 2022-03-15 00:00:00
            'd-m-Y',        // 15-03-2022
            'm/d/Y',        // 03/15/2022 (US format)
        ];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $raw);
                // Validasi: tanggal harus masuk akal (antara 1970 dan sekarang)
                if ($date && $date->year >= 1970 && $date->year <= Carbon::now()->year) {
                    return $date;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        // Fallback: coba Carbon::parse
        try {
            $date = Carbon::parse($raw);
            if ($date && $date->year >= 1970 && $date->year <= Carbon::now()->year) {
                return $date;
            }
        } catch (\Exception $e) {
            // ignore
        }

        return null;
    }

    public function handle()
    {
        $currentYear = Carbon::now()->year;
        $this->info("Memproses pengisian awal kuota cuti hingga tahun {$currentYear}...");

        // Iterasi dari sisi MPresensi (user mobile) — bukan MKaryawan
        // Ini untuk menghindari masalah cross-database query
        $users = MPresensi::whereNotNull('karyawan_id')->get();
        $this->info("Ditemukan {$users->count()} user dengan karyawan_id terhubung.");

        $created = 0;
        $skipped_tenure = 0;
        $skipped_exists = 0;
        $skipped_no_karyawan = 0;
        $skipped_bad_date = 0;

        foreach ($users as $user) {
            // Ambil data karyawan dari DB master via karyawan_id
            $karyawan = MKaryawan::find($user->karyawan_id);

            if (!$karyawan) {
                $skipped_no_karyawan++;
                continue;
            }

            // Parse tanggal masuk
            $joinDate = $this->parseJoinDate($karyawan->tgl_masuk);

            if (!$joinDate) {
                $skipped_bad_date++;
                $this->line("  [SKIP-DATE] {$karyawan->nama_karyawan} — format tgl_masuk tidak dikenali: [{$karyawan->tgl_masuk}]");
                continue;
            }

            // Cek total masa kerja
            $yearsWorked = Carbon::now()->diffInYears($joinDate);
            if ($yearsWorked < 1) {
                $skipped_tenure++;
                continue;
            }

            // Tahun pertama layak mendapat kuota: tahun bergabung + 1
            $firstEligibleYear = $joinDate->year + 1;

            for ($year = $firstEligibleYear; $year <= $currentYear; $year++) {
                // Pastikan anniversary di tahun ini sudah terlewati
                try {
                    $anniversary = $joinDate->copy()->year($year);
                    if ($anniversary->isFuture()) {
                        continue; // Belum waktunya
                    }
                } catch (\Exception $e) {
                    continue;
                }

                // Cek apakah record sudah ada
                if (LeaveBalance::where('user_id', $user->id)->where('year', $year)->exists()) {
                    $skipped_exists++;
                    continue;
                }

                // Buat record baru
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

        $msg = "Selesai. Dibuat: {$created} | Sudah ada: {$skipped_exists} | Masa kerja <1th: {$skipped_tenure} | Tanggal invalid: {$skipped_bad_date} | Karyawan tidak ditemukan: {$skipped_no_karyawan}";
        $this->info($msg);
        Log::channel('daily')->info('SeedInitialLeaveQuota: ' . $msg);
    }
}
