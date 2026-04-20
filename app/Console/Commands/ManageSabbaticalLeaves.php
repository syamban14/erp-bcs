<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MKaryawan;
use App\Models\SabbaticalLeave;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ManageSabbaticalLeaves extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sabbatical:manage';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Provide or expire sabbatical leaves for eligible employees with multiple of 5 years tenure.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Log::info('[SabbaticalLeave] Command run started at ' . now()->toDateTimeString());
        $now = Carbon::now();

        // Cari Karyawan yang kelipatan 5 tahun dan peringatannya tepat jatuh di hari ini
        // Kita loop melalui batch jika jumlah data besar, untuk saat ini `get()` aman.
        $employees = MKaryawan::whereNotNull('tgl_masuk')->get();
        
        $countAwarded = 0;

        foreach ($employees as $employee) {
            try {
                // Formatting format masuk (dd/mm/yyyy atau yyyy-mm-dd)
                $rawDate = $employee->tgl_masuk;
                $joinDate = null;
                
                if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $rawDate)) {
                    $joinDate = Carbon::createFromFormat('d/m/Y', $rawDate);
                } elseif (preg_match('/^\d{4}-\d{2}-\d{2}/', $rawDate)) {
                    $joinDate = Carbon::parse($rawDate);
                }

                if (!$joinDate) {
                    continue;
                }

                // Cek if anniversary is today
                if ($joinDate->month === $now->month && $joinDate->day === $now->day) {
                    $yearsOfService = $joinDate->diffInYears($now);
                    
                    // Kelipatan 5 tahun (5, 10, 15, dst)
                    if ($yearsOfService > 0 && $yearsOfService % 5 === 0) {
                        
                        // User MPresensi yang terhubung dengan MKaryawan ini
                        $user = \App\Models\MPresensi::where('karyawan_id', $employee->id)->first();
                        
                        // Failover: cek juga relasi terbalik jika karyawan_id null tapi presensiAccount ada (tergantung sync)
                        if (!$user) {
                            $user = \App\Models\MPresensi::whereHas('karyawan', function($q) use ($employee) {
                                $q->where('id', $employee->id);
                            })->first();
                        }
                        
                        if ($user) {
                            DB::transaction(function () use ($user, $now, $yearsOfService) {
                                // 1. Kadaluarsakan sisa cuti besar yang lama dengan mengubah expires_at ke kemarin
                                SabbaticalLeave::where('user_id', $user->id)
                                    ->where('expires_at', '>=', $now->copy()->startOfDay())
                                    ->update(['expires_at' => $now->copy()->subDay()]);
                                
                                // 2. Insert saldo cuti besar baru (10 hari, masa berlaku 5 tahun ke depan)
                                SabbaticalLeave::create([
                                    'user_id' => $user->id,
                                    'quota' => 10,
                                    'used' => 0,
                                    'expires_at' => $now->copy()->addYears(5)->endOfDay(),
                                ]);
                            });

                            $countAwarded++;
                            Log::info("[SabbaticalLeave] Cuti besar 10 hari telah diberikan kepada {$user->name} untuk ulang tahun kerja ke-{$yearsOfService}");
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::error("[SabbaticalLeave] Error processing employee ID {$employee->id}: " . $e->getMessage());
            }
        }

        $this->info("Berhasil! $countAwarded akun karyawan diberikan cuti besar.");
        Log::info("[SabbaticalLeave] Command run completed. Total awarded: $countAwarded");
    }
}
