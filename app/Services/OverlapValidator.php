<?php

namespace App\Services;

use App\Models\Leave;
use App\Models\OvertimeRequest;
use App\Models\OutstationRequest;
use App\Models\PermissionRequest;
use App\Models\Presence;
use Carbon\Carbon;

/**
 * OverlapValidator
 *
 * Memeriksa apakah rentang tanggal pengajuan baru bertabrakan (overlap)
 * dengan pengajuan yang sudah ada (status pending/approved) lintas-modul.
 */
class OverlapValidator
{
    private const ACTIVE_STATUSES = ['pending', 'approved'];

    public static function check(int $userId, string $startDate, string $endDate, ?string $excludeModule = null, ?int $excludeId = null): ?string
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end   = Carbon::parse($endDate)->endOfDay();

        // ── 1. Cek tabel Cuti (leaves) ──
        $query = Leave::where('user_id', $userId)->whereIn('status', self::ACTIVE_STATUSES);
        if ($excludeModule === 'leave' && $excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        $conflict = $query->where(function ($q) use ($start, $end) {
            $q->whereBetween('start_date', [$start, $end])
              ->orWhereBetween('end_date', [$start, $end])
              ->orWhere(function ($q2) use ($start, $end) {
                  $q2->where('start_date', '<=', $start)->where('end_date', '>=', $end);
              });
        })->first();

        if ($conflict) {
            $label  = $conflict->type ?? 'Cuti';
            $status = $conflict->status === 'pending' ? 'Menunggu' : 'Disetujui';
            $from   = Carbon::parse($conflict->start_date)->format('d M Y');
            $to     = Carbon::parse($conflict->end_date)->format('d M Y');
            return "Gagal mengajukan: Tanggal tersebut bertabrakan dengan pengajuan {$label} Anda ({$from} – {$to}) yang berstatus {$status}.";
        }

        // ── 2. Cek tabel Izin (permission_requests) ──
        $query = PermissionRequest::where('user_id', $userId)->whereIn('status', self::ACTIVE_STATUSES);
        if ($excludeModule === 'permission' && $excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        $conflict = $query->where(function ($q) use ($start, $end) {
            $q->whereBetween('start_date', [$start, $end])
              ->orWhereBetween('end_date', [$start, $end])
              ->orWhere(function ($q2) use ($start, $end) {
                  $q2->where('start_date', '<=', $start)->where('end_date', '>=', $end);
              });
        })->first();

        if ($conflict) {
            $label  = $conflict->type ?? 'Izin';
            $status = $conflict->status === 'pending' ? 'Menunggu' : 'Disetujui';
            $date   = Carbon::parse($conflict->start_date)->format('d M Y');
            return "Gagal mengajukan: Tanggal {$date} bertabrakan dengan pengajuan {$label} Anda yang berstatus {$status}.";
        }

        // ── 3. Cek tabel Lembur (overtime_requests) ──
        $query = OvertimeRequest::where('user_id', $userId)->whereIn('status', self::ACTIVE_STATUSES);
        if ($excludeModule === 'overtime' && $excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        $conflict = $query->where(function ($q) use ($start, $end) {
            $q->whereBetween('start_date', [$start, $end])
              ->orWhereBetween('end_date', [$start, $end])
              ->orWhere(function ($q2) use ($start, $end) {
                  $q2->where('start_date', '<=', $start)->where('end_date', '>=', $end);
              });
        })->first();

        if ($conflict) {
            $status = $conflict->status === 'pending' ? 'Menunggu' : 'Disetujui';
            $from   = Carbon::parse($conflict->start_date)->format('d M Y');
            $to     = Carbon::parse($conflict->end_date)->format('d M Y');
            return "Gagal mengajukan: Tanggal tersebut bertabrakan dengan pengajuan Lembur Anda ({$from} – {$to}) yang berstatus {$status}.";
        }

        // ── 4. Cek tabel Dinas Luar (outstation_requests) ──
        $query = OutstationRequest::where('user_id', $userId)->whereIn('status', self::ACTIVE_STATUSES);
        if ($excludeModule === 'outstation' && $excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        $conflict = $query->where(function ($q) use ($start, $end) {
            $q->whereBetween('start_date', [$start, $end])
              ->orWhereBetween('end_date', [$start, $end])
              ->orWhere(function ($q2) use ($start, $end) {
                  $q2->where('start_date', '<=', $start)->where('end_date', '>=', $end);
              });
        })->first();

        if ($conflict) {
            $label  = $conflict->task_type ?? 'Dinas Luar';
            $status = $conflict->status === 'pending' ? 'Menunggu' : 'Disetujui';
            $from   = Carbon::parse($conflict->start_date)->format('d M Y');
            $to     = Carbon::parse($conflict->end_date)->format('d M Y');
            return "Gagal mengajukan: Tanggal tersebut bertabrakan dengan pengajuan {$label} Anda ({$from} – {$to}) yang berstatus {$status}.";
        }

        // ── 5. [SKENARIO 2] Cek Presensi — blokir jika sudah clock-in hari ini ──
        // Hanya berlaku saat mengajukan Cuti untuk hari ini (Izin diperbolehkan karena terkait kehadiran parsial).
        if (in_array($excludeModule, ['leave'])) {
            $today = Carbon::today()->format('Y-m-d');
            if ($startDate === $today) {
                $hasPresence = Presence::where('user_id', $userId)
                    ->where('date', $today)
                    ->whereNotNull('clock_in')
                    ->exists();

                if ($hasPresence) {
                    return 'Anda sudah melakukan presensi masuk (hadir) pada tanggal tersebut. Pengajuan tidak dapat diproses.';
                }
            }
        }

        return null; // Tidak ada konflik
    }
}
