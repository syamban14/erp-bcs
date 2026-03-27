<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Leave;
use App\Models\Presence;
use App\Models\PermissionRequest;
use App\Models\ShiftSchedule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RecapController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2100',
        ]);

        $user = $request->user();
        $month = $request->month;
        $year = $request->year;

        // Get start and end date of the month
        $startDate = Carbon::create($year, $month, 1)->startOfDay();
        $endDate = $startDate->copy()->endOfMonth();

        // Get all presences for the month
        $presences = Presence::where('user_id', $user->id)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        // Get approved permission requests for the month
        $permissions = PermissionRequest::where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereBetween('start_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get()
            ->keyBy('start_date');

        // Get shift schedules for the month (for holidays/weekends)
        $shifts = ShiftSchedule::where('user_id', $user->id)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->with('shiftCode')
            ->get()
            ->keyBy('date');

        // Initialize summary counters
        $summary = [
            'present' => 0,
            'sick' => 0,
            'excuse' => 0,
            'alpha' => 0,
            'late' => 0,
        ];

        // Build daily history
        $dailyHistory = [];
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $dateStr = $currentDate->format('Y-m-d');
            
            // Check if there's a presence record
            $presence = $presences->get($dateStr);
            
            // Check if there's a permission record
            $permission = $permissions->get($dateStr);
            
            // Check if it's a scheduled off day
            $shift = $shifts->get($dateStr);
            $isOff = $shift && $shift->shiftCode && $shift->shiftCode->is_off;

            // Determine status and update summary
            $status = null;
            $clockIn = null;
            $clockOut = null;
            $lateMinutes = 0;
            $lateDuration = null;

            if ($presence && $presence->clock_in) {
                // Has presence record
                $clockIn = $presence->clock_in;
                $clockOut = $presence->clock_out;
                $lateMinutes = $presence->late_minutes ?? 0;

                if ($presence->status === 'Terlambat' || $lateMinutes > 0) {
                    $status = 'Terlambat';
                    $summary['late']++;
                    $lateDuration = $this->formatLateDuration($lateMinutes);
                } else {
                    $status = 'Hadir';
                }
                $summary['present']++;
            } elseif ($permission) {
                // Has approved permission
                if ($permission->type === 'sick') {
                    $status = 'Sakit';
                    $summary['sick']++;
                } else {
                    $status = 'Izin';
                    $summary['excuse']++;
                }
            } elseif ($isOff) {
                // Scheduled off day
                $status = 'Libur';
            } else {
                // No record - Alpha (only if not future date)
                if ($currentDate->lte(now())) {
                    $status = 'Alpha';
                    $summary['alpha']++;
                } else {
                    $status = '-';
                }
            }

            $dailyHistory[] = [
                'date' => $dateStr,
                'status' => $status,
                'clock_in' => $clockIn,
                'clock_out' => $clockOut,
                'late_minutes' => $lateMinutes,
                'late_duration' => $lateDuration,
            ];

            $currentDate->addDay();
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'summary' => $summary,
                'daily_history' => $dailyHistory,
            ],
        ]);
    }

    /**
     * Format late minutes to "Xj Ym" or "Xm" format
     */
    private function formatLateDuration($minutes)
    {
        if ($minutes <= 0) {
            return null;
        }

        $hours = floor($minutes / 60);
        $mins = $minutes % 60;

        if ($hours > 0) {
            if ($mins > 0) {
                return "{$hours}j {$mins}m";
            }
            return "{$hours}j";
        }

        return "{$mins}m";
    }
    /**
     * Export rekap absensi bulanan ke PDF atau Excel.
     *
     * GET /api/v1/recaps/export?month=3&year=2026&format=pdf&token=TOKEN
     * Auth: query param ?token= (untuk kompatibilitas Flutter url_launcher)
     */
    public function export(Request $request)
    {
        $request->validate([
            'month'  => 'required|integer|min:1|max:12',
            'year'   => 'required|integer|min:2020|max:2100',
            'format' => 'nullable|in:pdf,excel',
        ]);

        $month  = (int) $request->month;
        $year   = (int) $request->year;
        $format = strtolower($request->format ?? 'pdf');
        $user   = $request->user();

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate   = $startDate->copy()->endOfMonth();

        // Data kehadiran
        $presences = Presence::where('user_id', $user->id)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->orderBy('date')
            ->get()
            ->keyBy(fn ($p) => Carbon::parse($p->date)->format('Y-m-d'));

        // Data cuti yang approved
        $leaves = Leave::where('user_id', $user->id)
            ->where('status', 'approved')
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                  ->orWhereBetween('end_date', [$startDate, $endDate]);
            })->get();

        $leaveDates = [];
        foreach ($leaves as $leave) {
            $period = Carbon::parse($leave->start_date)
                ->daysUntil(Carbon::parse($leave->end_date)->addDay());
            foreach ($period as $day) {
                $key = $day->format('Y-m-d');
                if ($day->between($startDate, $endDate)) {
                    $leaveDates[$key] = $leave->leave_type ?? 'Cuti';
                }
            }
        }

        // Data izin yang approved
        $permissions = PermissionRequest::where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereBetween('start_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get();
        foreach ($permissions as $perm) {
            $permPeriod = Carbon::parse($perm->start_date)
                ->daysUntil(Carbon::parse($perm->end_date ?? $perm->start_date)->addDay());
            foreach ($permPeriod as $day) {
                $key = $day->format('Y-m-d');
                if ($day->between($startDate, $endDate) && !isset($leaveDates[$key])) {
                    $leaveDates[$key] = $perm->type === 'sick' ? 'Sakit' : 'Izin';
                }
            }
        }

        // Bangun daily records & hitung summary
        $dailyRecords     = [];
        $totalHadir       = 0;
        $totalTerlambat   = 0;
        $totalLateMinutes = 0;
        $totalCuti        = 0;
        $totalIzin        = 0;
        $totalAlpha       = 0;
        $workingDays      = 0;

        $current = $startDate->copy();
        while ($current->lte($endDate)) {
            $dateKey   = $current->format('Y-m-d');
            $dayLabel  = $current->locale('id')->isoFormat('dddd');

            if ($current->isWeekend()) {
                $current->addDay();
                continue;
            }

            $workingDays++;
            $presence  = $presences->get($dateKey);
            $leaveType = $leaveDates[$dateKey] ?? null;

            if ($presence && $presence->clock_in) {
                $isLate = ($presence->late_minutes ?? 0) > 0;
                $status = $isLate ? 'Terlambat' : 'Hadir';
                $notes  = $isLate ? "Terlambat {$presence->late_minutes} menit" : 'Tepat Waktu';
                $totalHadir++;
                if ($isLate) { $totalTerlambat++; $totalLateMinutes += $presence->late_minutes; }
            } elseif ($leaveType) {
                $isCuti = stripos($leaveType, 'cuti') !== false || stripos($leaveType, 'leave') !== false;
                $status = $isCuti ? 'Cuti' : ($leaveType === 'Sakit' ? 'Sakit' : 'Izin');
                $notes  = $leaveType;
                if ($isCuti) $totalCuti++; else $totalIzin++;
            } elseif ($current->lte(now())) {
                $status = 'Alpha';
                $notes  = '-';
                $totalAlpha++;
            } else {
                $status = '-';
                $notes  = 'Belum terjadi';
            }

            $dailyRecords[] = [
                'date'      => $current->format('d M Y'),
                'day'       => $dayLabel,
                'clock_in'  => $presence?->clock_in  ? substr($presence->clock_in,  0, 5) : '-',
                'clock_out' => $presence?->clock_out ? substr($presence->clock_out, 0, 5) : '-',
                'status'    => $status,
                'notes'     => $notes,
            ];

            $current->addDay();
        }

        $summary = [
            'working_days'       => $workingDays,
            'total_hadir'        => $totalHadir,
            'total_terlambat'    => $totalTerlambat,
            'total_late_minutes' => $totalLateMinutes,
            'total_cuti'         => $totalCuti,
            'total_izin'         => $totalIzin,
            'total_alpha'        => $totalAlpha,
        ];

        $periodLabel = Carbon::create($year, $month, 1)->locale('id')->isoFormat('MMMM YYYY');
        $karyawan    = $user->karyawan ?? null;
        $filename    = "Rekap_Absensi_{$year}_" . str_pad($month, 2, '0', STR_PAD_LEFT);

        Log::info('RecapExport', ['user_id' => $user->id, 'month' => $month, 'year' => $year, 'format' => $format]);

        if ($format === 'excel') {
            return $this->exportExcel($filename, $user, $karyawan, $periodLabel, $summary, $dailyRecords);
        }
        return $this->exportPdf($filename, $user, $karyawan, $periodLabel, $summary, $dailyRecords);
    }

    private function exportPdf($filename, $user, $karyawan, $periodLabel, $summary, $dailyRecords)
    {
        if (!class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return response()->json([
                'meta' => ['code' => 500, 'status' => 'error', 'message' => 'Library PDF belum terinstall di server. Jalankan: composer install'],
                'data' => null,
            ], 500);
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.recap_pdf',
            compact('user', 'karyawan', 'periodLabel', 'summary', 'dailyRecords')
        );
        $pdf->setPaper('A4', 'portrait');
        return $pdf->download("{$filename}.pdf");
    }

    private function exportExcel($filename, $user, $karyawan, $periodLabel, $summary, $dailyRecords)
    {
        $data = [];
        $data[] = ['PT. BCS LOGISTICS — Rekap Absensi Karyawan'];
        $data[] = ['Periode', $periodLabel];
        $data[] = ['Nama',    $user->name];
        $data[] = ['Email',   $user->email];
        $data[] = [];
        $data[] = ['RINGKASAN KEHADIRAN'];
        $data[] = ['Hari Kerja', 'Hadir', 'Terlambat', 'Total Terlambat (menit)', 'Cuti', 'Izin', 'Alpha'];
        $data[] = [
            $summary['working_days'], $summary['total_hadir'], $summary['total_terlambat'],
            $summary['total_late_minutes'], $summary['total_cuti'], $summary['total_izin'], $summary['total_alpha'],
        ];
        $data[] = [];
        $data[] = ['No', 'Tanggal', 'Hari', 'Jam Masuk', 'Jam Keluar', 'Status', 'Keterangan'];
        foreach ($dailyRecords as $i => $row) {
            $data[] = [$i + 1, $row['date'], $row['day'], $row['clock_in'], $row['clock_out'], $row['status'], $row['notes']];
        }

        \Excel::create($filename, function ($excel) use ($data, $user) {
            $excel->setTitle("Rekap Absensi — {$user->name}");
            $excel->setCreator('Sistem Presensi BCS');
            $excel->sheet('Rekap', function ($sheet) use ($data) {
                $sheet->fromArray($data, null, 'A1', false, false);
                $sheet->row(1, fn ($row) => $row->setFontSize(12)->setFontWeight('bold'));
                $sheet->setAutoSize(true);
            });
        })->download('xlsx');
    }
}
