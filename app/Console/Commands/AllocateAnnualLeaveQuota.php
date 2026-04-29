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
        $currentYear = (int) Carbon::now()->year;

        $this->info("Menjalankan tugas alokasi cuti untuk tahun {$currentYear}...");

        $employees = MKaryawan::with('presensiAccount')
            ->where(function ($query) {
                $query->where('aktif', 'Y')
                      ->orWhere('aktif', '1')
                      ->orWhereNull('aktif');
            })
            ->get();

        $count = 0;
        $skipped = 0;
        $alreadyAllocated = 0;

        foreach ($employees as $karyawan) {
            if (!$karyawan->presensiAccount) {
                continue;
            }

            $joinDate = null;
            $rawDate = $karyawan->tgl_masuk;

            try {
                if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $rawDate)) {
                    $joinDate = Carbon::createFromFormat('d/m/Y', $rawDate)->startOfDay();
                } elseif (preg_match('/^\d{4}-\d{2}-\d{2}/', $rawDate)) {
                    $joinDate = Carbon::parse(substr($rawDate, 0, 10))->startOfDay();
                }
            } catch (\Exception $e) {
                $joinDate = null;
            }

            if (!$joinDate || $joinDate->diffInYears(Carbon::now()) < 1) {
                $skipped++;
                continue;
            }

            // Hitung tanggal anniversary di tahun ini
            $anniversaryThisYear = $joinDate->copy()->year($currentYear);
            
            // Jika anniversary belum tiba tahun ini, lewati
            if (Carbon::now()->startOfDay()->lessThan($anniversaryThisYear)) {
                $skipped++;
                continue;
            }

            $user = $karyawan->presensiAccount;

            // Cek apakah sudah dapat kuota tahun ini (quota > 0)
            $balance = LeaveBalance::firstOrCreate(
                ['user_id' => $user->id, 'year' => $currentYear],
                ['quota' => 0, 'used' => 0]
            );

            // Jika kuota masih 0 atau minus (belum dialokasikan atau ada hutang cuti)
            if ($balance->quota <= 0) {
                $newQuota = $balance->quota + 12; // Tambahkan 12 agar hutang cuti (minus) terpotong
                
                $balance->update([
                    'quota' => $newQuota,
                    // Kita tidak mereset 'used' karena jika ada cuti diawal tahun, itu harus tetap tercatat
                ]);
                $count++;
                $this->line("  [OK] {$karyawan->nama_karyawan} — Kuota cuti ditambahkan 12 (Total: {$newQuota}).");
            } else {
                $alreadyAllocated++;
            }
        }

        $message = "Alokasi Cuti Tahunan: {$count} karyawan mendapat kuota baru. {$alreadyAllocated} sudah dialokasikan sebelumnya. {$skipped} dilewati (masa kerja < 1 thn / belum anniversary).";
        $this->info($message);
        Log::channel('daily')->info('LeaveAllocationSync: ' . $message);
    }
}
