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

        $formats = [
            'd/m/Y',
            'd/n/Y',
            'Y-m-d',
            'Y-m-d H:i:s',
            'd-m-Y',
            'n/j/Y',
        ];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $raw);
                if ($date && $date->year >= 1970 && $date->year <= Carbon::now()->year) {
                    return $date;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        try {
            $date = Carbon::parse($raw);
            if ($date && $date->year >= 1970 && $date->year <= Carbon::now()->year) {
                return $date;
            }
        } catch (\Exception $e) {}

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

        foreach ($users as $user) {
            // Ambil tgl_masuk dari tabel m_karyawan di pgsql_master
            $karyawan = DB::connection('pgsql_master')
                ->table('m_karyawan')
                ->where('id', $user->karyawan_id)
                ->first(['nama_karyawan', 'tgl_masuk', 'aktif']);

            if (!$karyawan) {
                $skipped_no_karyawan++;
                continue;
            }

            $joinDate = $this->parseJoinDate($karyawan->tgl_masuk);

            if (!$joinDate) {
                $skipped_bad_date++;
                $this->line("  [SKIP-DATE] {$karyawan->nama_karyawan} — format tgl_masuk tidak dikenali: [{$karyawan->tgl_masuk}]");
                continue;
            }

            $yearsWorked = Carbon::now()->diffInYears($joinDate);
            if ($yearsWorked < 1) {
                $skipped_tenure++;
                continue;
            }

            $firstEligibleYear = $joinDate->year + 1;

            for ($year = $firstEligibleYear; $year <= $currentYear; $year++) {
                try {
                    $anniversary = $joinDate->copy()->year($year);
                    if ($anniversary->isFuture()) {
                        continue;
                    }
                } catch (\Exception $e) {
                    continue;
                }

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
