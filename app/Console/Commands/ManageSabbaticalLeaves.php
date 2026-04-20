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

                // Kalkulasi usia kerja dalam hitungan tahun (pecahan ke bawah)
                $yearsOfService = $joinDate->diffInYears($now);
                
                // Syarat: Minimal sudah 5 tahun bergabung
                if ($yearsOfService >= 5) {
                    // Cari "Milestone Kelipatan 5" terakhir yang dicapai user (misal: usia 6 tahun -> milestone 5)
                    $milestone = floor($yearsOfService / 5) * 5; 
                    
                    // Tanggal persis karyawan mencapai milestone tersebut
                    $milestoneDate = $joinDate->copy()->addYears($milestone);
                    
                    // Masa berlaku dari milestone tersebut adalah 5 tahun ke depan (pukul 23:59:59)
                    $expiryDate = $milestoneDate->copy()->addYears(5)->endOfDay();

                    // Jika masa berlakunya masih aktif (artinya kedaluwarsa belum lewat hari ini)
                    if ($expiryDate->isFuture()) {
                    
                        $user = \App\Models\MPresensi::where('karyawan_id', $employee->id)->first();
                        
                        if (!$user) {
                            $user = \App\Models\MPresensi::whereHas('karyawan', function($q) use ($employee) {
                                $q->where('id', $employee->id);
                            })->first();
                        }
                        
                        if ($user) {
                            // Cek apakah untuk MILSTONE ini user TERSEBUT sudah pernah dibuatkan cuti besar?
                            // Kita cukup cek apakah dia punya row dengan expires_at == $expiryDate
                            $hasBeenAwarded = SabbaticalLeave::where('user_id', $user->id)
                                ->whereDate('expires_at', $expiryDate->format('Y-m-d'))
                                ->exists();

                            if (!$hasBeenAwarded) {
                                DB::transaction(function () use ($user, $expiryDate) {
                                    // 1. Kadaluarsakan semua sisa cuti besar yang rentangnya lebih lama/sebelumnya (prevent duplicate active)
                                    SabbaticalLeave::where('user_id', $user->id)
                                        ->where('expires_at', '>=', Carbon::now()->startOfDay())
                                        ->update(['expires_at' => Carbon::now()->subDay()]);
                                    
                                    // 2. Insert saldo cuti besar baru (10 hari)
                                    SabbaticalLeave::create([
                                        'user_id' => $user->id,
                                        'quota' => 10,
                                        'used' => 0,
                                        'expires_at' => $expiryDate,
                                    ]);
                                });

                                $countAwarded++;
                                Log::info("[SabbaticalLeave] Backfill/Award: Cuti besar 10 hari telah diberikan kepada {$user->name} untuk milestone ke-{$milestone} tahun (berlaku s.d {$expiryDate->format('Y-m-d')})");
                            }
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
