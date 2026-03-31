<?php

namespace App\Services;

use App\Models\Leave;
use App\Models\OvertimeRequest;
use App\Models\OutstationRequest;
use App\Models\PermissionRequest;
use Carbon\Carbon;

/**
 * OverlapValidator
 *
 * Memeriksa apakah rentang tanggal pengajuan baru bertabrakan (overlap)
 * dengan pengajuan yang sudah ada (status pending/approved) lintas-modul.
 *
 * Penggunaan:
 *   $conflict = OverlapValidator::check($userId, $startDate, $endDate, exclude: 'overtime');
 *   if ($conflict) {
 *       return response()->json(['meta' => [..., 'message' => $conflict]], 422);
 *   }
 */
class OverlapValidator
{
    // Status yang dianggap aktif (yang perlu dicek)
    private const ACTIVE_STATUSES = ['pending', 'approved'];

    /**
     * Cek overlap lintas-modul untuk seorang user.
     *
     * @param  int         $userId
     * @param  string      $startDate  Format: Y-m-d
     * @param  string      $endDate    Format: Y-m-d
     * @param  string|null $exclude    Modul yang dikecualikan dari pengecekan (nama modul itu sendiri)
     * @return string|null Pesan konflik (jika ada), atau null (tidak ada konflik)
     */
    public static function check(int $userId, string $startDate, string $endDate, ?string $exclude = null): ?string
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end   = Carbon::parse($endDate)->endOfDay();

        // ── 1. Cek tabel Cuti (leaves) ──
        if ($exclude !== 'leave') {
            $conflict = Leave::where('user_id', $userId)
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->where(function ($q) use ($start, $end) {
                    $q->whereBetween('start_date', [$start, $end])
                      ->orWhereBetween('end_date', [$start, $end])
                      ->orWhere(function ($q2) use ($start, $end) {
                          $q2->where('start_date', '<=', $start)
                             ->where('end_date', '>=', $end);
                      });
                })->first();

            if ($conflict) {
                $label   = $conflict->type ?? 'Cuti';
                $status  = $conflict->status === 'pending' ? 'Menunggu' : 'Disetujui';
                $from    = Carbon::parse($conflict->start_date)->format('d M Y');
                $to      = Carbon::parse($conflict->end_date)->format('d M Y');
                return "Gagal mengajukan: Tanggal tersebut bertabrakan dengan pengajuan {$label} Anda ({$from} – {$to}) yang berstatus {$status}.";
            }
        }

        // ── 2. Cek tabel Izin (permission_requests) ──
        if ($exclude !== 'permission') {
            $conflict = PermissionRequest::where('user_id', $userId)
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->where(function ($q) use ($start, $end) {
                    $q->whereBetween('start_date', [$start, $end])
                      ->orWhereBetween('end_date', [$start, $end])
                      ->orWhere(function ($q2) use ($start, $end) {
                          $q2->where('start_date', '<=', $start)
                             ->where('end_date', '>=', $end);
                      });
                })->first();

            if ($conflict) {
                $label  = $conflict->type ?? 'Izin';
                $status = $conflict->status === 'pending' ? 'Menunggu' : 'Disetujui';
                $date   = Carbon::parse($conflict->start_date)->format('d M Y');
                return "Gagal mengajukan: Tanggal {$date} bertabrakan dengan pengajuan {$label} Anda yang berstatus {$status}.";
            }
        }

        // ── 3. Cek tabel Lembur (overtime_requests) ──
        if ($exclude !== 'overtime') {
            $conflict = OvertimeRequest::where('user_id', $userId)
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->where(function ($q) use ($start, $end) {
                    $q->whereBetween('start_date', [$start, $end])
                      ->orWhereBetween('end_date', [$start, $end])
                      ->orWhere(function ($q2) use ($start, $end) {
                          $q2->where('start_date', '<=', $start)
                             ->where('end_date', '>=', $end);
                      });
                })->first();

            if ($conflict) {
                $status = $conflict->status === 'pending' ? 'Menunggu' : 'Disetujui';
                $from   = Carbon::parse($conflict->start_date)->format('d M Y');
                $to     = Carbon::parse($conflict->end_date)->format('d M Y');
                return "Gagal mengajukan: Tanggal tersebut bertabrakan dengan pengajuan Lembur Anda ({$from} – {$to}) yang berstatus {$status}.";
            }
        }

        // ── 4. Cek tabel Dinas Luar (outstation_requests) ──
        if ($exclude !== 'outstation') {
            $conflict = OutstationRequest::where('user_id', $userId)
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->where(function ($q) use ($start, $end) {
                    $q->whereBetween('start_date', [$start, $end])
                      ->orWhereBetween('end_date', [$start, $end])
                      ->orWhere(function ($q2) use ($start, $end) {
                          $q2->where('start_date', '<=', $start)
                             ->where('end_date', '>=', $end);
                      });
                })->first();

            if ($conflict) {
                $label  = $conflict->task_type ?? 'Dinas Luar';
                $status = $conflict->status === 'pending' ? 'Menunggu' : 'Disetujui';
                $from   = Carbon::parse($conflict->start_date)->format('d M Y');
                $to     = Carbon::parse($conflict->end_date)->format('d M Y');
                return "Gagal mengajukan: Tanggal tersebut bertabrakan dengan pengajuan {$label} Anda ({$from} – {$to}) yang berstatus {$status}.";
            }
        }

        return null; // Tidak ada konflik
    }
}
