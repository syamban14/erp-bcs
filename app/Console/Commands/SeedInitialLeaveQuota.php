<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\LeaveBalance;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SeedInitialLeaveQuota extends Command
{
    protected $signature = 'leave:seed-initial-quota';

    protected $description = 'Mengisi kuota cuti awal (12 hari) untuk semua user mobile yang sudah bekerja >= 1 tahun, untuk setiap tahun yang belum ada recordnya.';

    private function parseJoinDate(?string $raw): ?Carbon
    {
        if (empty($raw) || in_array($raw, ['0000-00-00', '00/00/0000', '0000-00-00 00:00:00'])) {
            return null;
        }

        $raw = trim($raw);
        $date = null;

        try {
            // Prioritas 1: YYYY-MM-DD atau YYYY-MM-DD HH:MM:SS (format PostgreSQL/ISO)
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $m)) {
                $date = Carbon::createFromFormat('Y-m-d', "{$m[1]}-{$m[2]}-{$m[3]}");

            // Prioritas 2: DD/MM/YYYY (format lokal Indonesia, dua digit semua)
            } elseif (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $raw, $m)) {
                $date = Carbon::createFromFormat('d/m/Y', $raw);

            // Prioritas 3: D/M/YYYY atau DD/M/YYYY (tanpa zero-padding)
            } elseif (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $raw, $m)) {
                $date = Carbon::createFromFormat('j/n/Y', $raw);

            // Prioritas 4: DD-MM-YYYY
            } elseif (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $raw, $m)) {
                $date = Carbon::createFromFormat('d-m-Y', $raw);
            }
        } catch (\Exception $e) {
            return null;
        }

        if ($date && $date->year >= 1970 && $date->year <= Carbon::now()->year) {
            return $date;
        }

        return null;
    }

    public function handle()
    {
        $currentYear = Carbon::now()->year;
        $this->info("Memproses pengisian awal kuota cuti hingga tahun {$currentYear}...");

        // Ambil semua user dari pgsql_master (MPresensi) yang punya karyawan_id
        $users = DB::connection('pgsql_master')
            ->table('m_presensi')
            ->whereNotNull('karyawan_id')
            ->get(['id', 'karyawan_id', 'name']);

        $this->info("Ditemukan {$users->count()} user dengan karyawan_id terhubung.");

        $created = 0;
        $skipped_tenure = 0;
        $skipped_exists = 0;
        $skipped_no_karyawan = 0;
        $skipped_bad_date = 0;
        $debugCount = 0;

        foreach ($users as $user) {
            $karyawan = DB::connection('pgsql_master')
                ->table('m_karyawan')
                ->where('id', $user->karyawan_id)
                ->first(['nama_karyawan', 'tgl_masuk']);

            if (!$karyawan) {
                $skipped_no_karyawan++;
                continue;
            }

            $raw = trim((string) $karyawan->tgl_masuk);

            // Deteksi tahun dari format YYYY-MM-DD atau DD/MM/YYYY
            $joinYear = null;
            if (preg_match('/^(\d{4})-\d{2}-\d{2}/', $raw, $m)) {
                // Format ISO: YYYY-MM-DD
                $joinYear = (int) $m[1];
            } elseif (preg_match('/^\d{2}\/\d{2}\/(\d{4})$/', $raw, $m)) {
                // Format Lokal: DD/MM/YYYY
                $joinYear = (int) $m[1];
            }

            // Debug: cetak 3 user pertama untuk verifikasi
            if ($debugCount < 3) {
                $this->line("  [DEBUG] {$karyawan->nama_karyawan}: tgl_masuk=[{$raw}] joinYear=[{$joinYear}] currentYear=[{$currentYear}]");
                $debugCount++;
            }

            if (!$joinYear || $joinYear <= 0) {
                $skipped_bad_date++;
                continue;
            }

            // Cek: masa kerja >= 1 tahun (perbandingan tahun langsung)
            $yearsWorked = $currentYear - $joinYear;
            if ($yearsWorked < 1) {
                $skipped_tenure++;
                continue;
            }

            $firstEligibleYear = $joinYear + 1;

            for ($year = $firstEligibleYear; $year <= $currentYear; $year++) {
                // Cek di pgsql (default) apakah sudah ada record
                $exists = LeaveBalance::where('user_id', $user->id)
                    ->where('year', $year)
                    ->exists();

                if ($exists) {
                    $skipped_exists++;
                    continue;
                }

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
