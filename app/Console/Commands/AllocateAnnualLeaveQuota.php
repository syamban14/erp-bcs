<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MKaryawan;
use App\Models\LeaveBalance;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AllocateAnnualLeaveQuota extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leave:allocate-anniversary';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Alokasi otomatis kuota cuti berdasarkan peringatan tanggal masuk kerja (Join Date Anniversary) pakerja.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $todayDay = Carbon::now()->format('d');
        $todayMonth = Carbon::now()->format('m');
        $currentYear = Carbon::now()->format('Y');

        $this->info("Menjalankan tugas alokasi cuti untuk ulang tahun kerja tanggal {$todayDay}/{$todayMonth}...");

        $employees = MKaryawan::with('presensiAccount')
            ->where(function ($query) {
                $query->where('aktif', 'Y')
                      ->orWhere('aktif', '1')
                      ->orWhereNull('aktif');
            })
            ->where(function ($query) use ($todayDay, $todayMonth) {
                $query->where('tgl_masuk', 'LIKE', $todayDay . '/' . $todayMonth . '/%')
                      ->orWhere('tgl_masuk', 'LIKE', '%-' . $todayMonth . '-' . $todayDay);
            })
            ->get();

        $count = 0;
        $skipped = 0;

        foreach ($employees as $karyawan) {
            if (!$karyawan->presensiAccount) {
                continue;
            }

            // --- Validasi: Karyawan HARUS sudah bekerja >= 1 tahun ---
            $joinDate = null;
            $rawDate = $karyawan->tgl_masuk;

            // Coba parse format dd/MM/YYYY
            try {
                if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $rawDate)) {
                    $joinDate = Carbon::createFromFormat('d/m/Y', $rawDate);
                } elseif (preg_match('/^\d{4}-\d{2}-\d{2}/', $rawDate)) {
                    $joinDate = Carbon::parse($rawDate);
                }
            } catch (\Exception $e) {
                $joinDate = null;
            }

            // Lewati jika tanggal tidak bisa di-parse atau masa kerja < 1 tahun
            if (!$joinDate || Carbon::now()->diffInYears($joinDate) < 1) {
                $skipped++;
                $this->line("  [SKIP] {$karyawan->nama_karyawan} — masa kerja belum genap 1 tahun.");
                continue;
            }

            $user = $karyawan->presensiAccount;

            // Reset kuota cuti ke 12, dan riwayat "used" jadi 0 (refresh tahunan)
            LeaveBalance::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'year' => $currentYear,
                ],
                [
                    'quota' => 12,
                    'used' => 0,
                ]
            );

            $count++;
            $this->line("  [OK] {$karyawan->nama_karyawan} — Kuota cuti direset ke 12.");
        }

        $message = "Alokasi Cuti Tahunan Sukses: {$count} karyawan mendapat kuota 12, {$skipped} dilewati (masa kerja < 1 tahun atau tanggal tidak valid) dari {$employees->count()} total record.";
        $this->info($message);
        Log::channel('daily')->info('LeaveAllocationSync: ' . $message);
    }
}
