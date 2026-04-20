<?php

namespace App\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\LeaveBalance;
use App\Models\MKaryawan;
use Carbon\Carbon;

class LeaveQuotaController extends Controller
{
    /**
     * Cek kelayakan masa kerja >= 1 tahun dari tgl_masuk karyawan.
     */
    private function isEligibleForLeave($user): bool
    {
        // Coba cari via karyawan_id yang ada di user (MPresensi)
        $karyawan = null;

        if (!empty($user->karyawan_id)) {
            $karyawan = \App\Models\MKaryawan::find($user->karyawan_id);
        }

        // Fallback: cari MKaryawan yang presensiAccount-nya adalah user ini
        if (!$karyawan) {
            $karyawan = \App\Models\MKaryawan::whereHas('presensiAccount', function($q) use ($user) {
                $q->where('id', $user->id);
            })->first();
        }

        if (!$karyawan || empty($karyawan->tgl_masuk)) {
            return false;
        }

        try {
            $raw = $karyawan->tgl_masuk;
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $raw)) {
                $joinDate = \Carbon\Carbon::createFromFormat('d/m/Y', $raw);
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}/', $raw)) {
                $joinDate = \Carbon\Carbon::parse($raw);
            } else {
                return false;
            }
            return \Carbon\Carbon::now()->diffInYears($joinDate) >= 1;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get user's leave quota information
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $year = (int) $request->input('year', date('Y'));

        // Cari record yang sudah ada
        $balance = LeaveBalance::where('user_id', $user->id)
            ->where('year', $year)
            ->first();

        // Jika belum ada, cek kelayakan dan buat otomatis
        if (!$balance) {
            if ($this->isEligibleForLeave($user)) {
                $balance = LeaveBalance::create([
                    'user_id' => $user->id,
                    'year'    => $year,
                    'quota'   => 12,
                    'used'    => 0,
                ]);
            } else {
                // Belum layak (masa kerja < 1 tahun)
                return response()->json([
                    'meta' => ['code' => 200, 'status' => 'success', 'message' => 'Leave quota retrieved'],
                    'data' => [
                        'year'                => $year,
                        'annual_quota'        => 0,
                        'used_quota'          => 0,
                        'remaining_quota'     => 0,
                        'eligible'            => false,
                        'note'                => 'Masa kerja kurang dari 1 tahun.',
                        
                        // Default data Cuti Besar
                        'big_leave_quota'     => 0,
                        'big_leave_used'      => 0,
                        'big_leave_remaining' => 0,
                        'has_big_leave'       => false,
                    ]
                ]);
            }
        }

        // Ambil data Cuti Besar
        $sabbatical = $user->activeSabbaticalLeave;
        $bigLeaveQuota = $sabbatical ? $sabbatical->quota : 0;
        $bigLeaveUsed = $sabbatical ? $sabbatical->used : 0;
        $bigLeaveRemaining = $sabbatical ? $sabbatical->getRemainingQuota() : 0;
        $hasBigLeave = $sabbatical ? true : false;

        return response()->json([
            'meta' => ['code' => 200, 'status' => 'success', 'message' => 'Leave quota retrieved'],
            'data' => [
                'year'                => $balance->year,
                'annual_quota'        => $balance->quota,
                'used_quota'          => $balance->used,
                'remaining_quota'     => $balance->getRemainingQuota(),
                'eligible'            => true,
                
                // Tambahan data Cuti Besar (Sabbatical Leave)
                'big_leave_quota'     => $bigLeaveQuota,
                'big_leave_used'      => $bigLeaveUsed,
                'big_leave_remaining' => $bigLeaveRemaining,
                'has_big_leave'       => $hasBigLeave,
            ]
        ]);
    }
    
    /**
     * Get leave quota history (all years)
     */
    public function history(Request $request)
    {
        // $request->user() returns MPresensi model from API guard
        $user = $request->user();
        
        $balances = \App\Models\LeaveBalance::where('user_id', $user->id)
            ->orderBy('year', 'desc')
            ->get()
            ->map(function($balance) {
                return [
                    'year' => $balance->year,
                    'quota' => $balance->quota,
                    'used' => $balance->used,
                    'remaining' => $balance->getRemainingQuota(),
                ];
            });
        
        return response()->json([
            'meta' => [
                'code' => 200,
                'status' => 'success',
                'message' => 'Leave quota history retrieved'
            ],
            'data' => $balances
        ]);
    }
}
