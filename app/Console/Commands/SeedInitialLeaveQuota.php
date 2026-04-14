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

    protected $description = 'Mengisi kuota cuti awal (12 hari) untuk semua karyawan aktif yang sudah bekerja >= 1 tahun dan belum punya record di tahun berjalan.';

    public function handle()
    {
        $currentYear = Carbon::now()->year;
        $this->info("Memproses pengisian awal kuota cuti tahun {$currentYear}...");

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

            // Parse tanggal masuk
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

            // Lewati jika tanggal tidak valid atau masa kerja < 1 tahun
            if (!$joinDate || Carbon::now()->diffInYears($joinDate) < 1) {
                $skipped_tenure++;
                continue;
            }

            $user = $karyawan->presensiAccount;

            // Cek apakah sudah ada record untuk tahun ini
            $existing = LeaveBalance::where('user_id', $user->id)
                ->where('year', $currentYear)
                ->first();

            if ($existing) {
                $skipped_exists++;
                continue;
            }

            // Buat record baru
            LeaveBalance::create([
                'user_id' => $user->id,
                'year'    => $currentYear,
                'quota'   => 12,
                'used'    => 0,
            ]);

            $created++;
            $this->line("  [CREATE] {$karyawan->nama_karyawan} → quota 12 untuk tahun {$currentYear}");
        }

        $msg = "Selesai. Dibuat: {$created} | Sudah ada: {$skipped_exists} | Belum 1 tahun: {$skipped_tenure} | Tanpa akun: {$skipped_no_account}";
        $this->info($msg);
        Log::channel('daily')->info('SeedInitialLeaveQuota: ' . $msg);
    }
}
