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

        foreach ($employees as $karyawan) {
            if ($karyawan->presensiAccount) {
                $user = $karyawan->presensiAccount;

                // Reset kuota cuti ke 12, dan riwayat "used" jadi 0 secara utuh (refresh tahunan)
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
            }
        }

        $message = "Alokasi Cuti Tahunan Otomatis Sukses: {$count} karyawan (dari {$employees->count()} total record) berhasil dikembalikan sisa cutinya ke 12 bertepatan dengan ulang tahun bergabung mereka hari ini.";
        $this->info($message);
        Log::channel('daily')->info('LeaveAllocationSync: ' . $message);
    }
}
