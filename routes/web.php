<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminActionController;

Route::get('/', function () {
    return redirect('/admin');
});

// Approval Center - Simple UI for approve/reject
Route::get('/approval-center', function () {
    return view('approval-center');
});

// Approval Center - Data endpoints (no auth required for admin web)
Route::get('/approval-center/permissions', [App\Http\Controllers\ApprovalCenterController::class, 'getPermissions']);
Route::get('/approval-center/corrections', [App\Http\Controllers\ApprovalCenterController::class, 'getCorrections']);
Route::get('/approval-center/leaves', [App\Http\Controllers\ApprovalCenterController::class, 'getLeaves']);

// Admin action endpoints (simple API for approve/reject)
Route::prefix('admin-api')->group(function () {
    Route::post('/permissions/{id}/approve', [AdminActionController::class, 'approvePermission']);
    Route::post('/permissions/{id}/reject', [AdminActionController::class, 'rejectPermission']);
    Route::post('/corrections/{id}/approve', [AdminActionController::class, 'approveCorrection']);
    Route::post('/corrections/{id}/reject', [AdminActionController::class, 'rejectCorrection']);
    Route::post('/leaves/{id}/approve', [AdminActionController::class, 'approveLeave']);
    Route::post('/leaves/{id}/reject', [AdminActionController::class, 'rejectLeave']);
});

// ── Export Monthly Recap (GET langsung, tidak lewat Livewire AJAX) ─────────
Route::get('/admin/monthly-recap/export', function (\Illuminate\Http\Request $request) {
    if (! auth()->check()) {
        abort(403);
    }

    $month  = (int) $request->query('month', now()->month);
    $year   = (int) $request->query('year',  now()->year);
    $unitId = $request->query('unit');

    $service   = app(\App\Services\RecapService::class);
    $endDate   = \Carbon\Carbon::create($year, $month, 15);
    $startDate = $endDate->copy()->subMonth()->addDay();

    $query = \App\Models\MPresensi::query()->with('officeLocation')->orderBy('name');
    if ($unitId) {
        $query->where('office_location_id', $unitId);
    }
    $employees = $query->get();

    $period   = $startDate->format('d M Y') . ' - ' . $endDate->format('d M Y');
    $filename = "recap_presensi_{$month}_{$year}.xls";

    $headers = [
        'Content-Type'        => 'application/vnd.ms-excel',
        'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        'Pragma'              => 'no-cache',
        'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
        'Expires'             => '0',
    ];

    $callback = function () use ($employees, $service, $startDate, $endDate, $period) {
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office"
               xmlns:x="urn:schemas-microsoft-com:office:excel"
               xmlns="http://www.w3.org/TR/REC-html40">';
        echo '<head><meta charset="UTF-8">
            <style>
                body{font-family:Calibri,Arial,sans-serif;font-size:11pt}
                table{border-collapse:collapse}
                th{background:#1E3A5F;color:#FFF;font-weight:bold;text-align:center;padding:6px 8px;border:1px solid #AAA;font-size:10pt;white-space:nowrap}
                td{padding:5px 8px;border:1px solid #DDD;font-size:10pt;vertical-align:middle}
                tr:nth-child(even) td{background:#F5F8FF}
                .title{font-size:14pt;font-weight:bold;color:#1E3A5F}
                .subtitle{font-size:10pt;color:#555}
                .center{text-align:center}
                .num-zero{color:#BBBBBB;text-align:center}
                .num-val{color:#1E3A5F;font-weight:bold;text-align:center}
                .danger{color:#CC0000;font-weight:bold;text-align:center}
                .warning{color:#CC6600;font-weight:bold;text-align:center}
            </style></head><body>';

        echo '<table>';
        echo "<tr><td colspan='15' class='title'>Rekap Presensi Karyawan</td></tr>";
        echo "<tr><td colspan='15' class='subtitle'>Periode: {$period}</td></tr>";
        echo "<tr><td colspan='15' class='subtitle'>Dicetak: " . now()->format('d M Y H:i') . "</td></tr>";
        echo "<tr><td colspan='15'>&nbsp;</td></tr>";
        echo "<tr>
            <th>No</th><th>Nama Karyawan</th><th>Unit Kerja</th>
            <th>Hari Kerja</th><th>Hadir</th><th>Durasi (Jam)</th>
            <th>Cuti Tahunan</th><th>Cuti Spesial</th><th>Sakit</th>
            <th>Izin</th><th>Tugas Luar</th><th>Alpa</th>
            <th>Lembur (Jam)</th><th>Telat (Jam)</th><th>Pulang Awal (Jam)</th>
        </tr>";

        $no = 1;
        foreach ($employees as $emp) {
            $d = $service->getRecapData($emp, $startDate, $endDate);
            $alpaClass  = $d['alpa'] > 0 ? 'danger' : 'num-zero';
            $hadirClass = $d['total_kehadiran'] >= $d['total_hari_kerja'] ? 'num-val' : 'warning';
            echo "<tr>
                <td class='center'>{$no}</td>
                <td>" . e($emp->name) . "</td>
                <td>" . e($emp->officeLocation->name ?? '-') . "</td>
                <td class='center'>{$d['total_hari_kerja']}</td>
                <td class='{$hadirClass}'>{$d['total_kehadiran']}</td>
                <td class='center'>{$d['durasi_kehadiran']}</td>
                <td class='" . ($d['cuti_tahunan']  > 0 ? 'num-val' : 'num-zero') . "'>{$d['cuti_tahunan']}</td>
                <td class='" . ($d['cuti_special']  > 0 ? 'num-val' : 'num-zero') . "'>{$d['cuti_special']}</td>
                <td class='" . ($d['cuti_sakit']    > 0 ? 'warning' : 'num-zero') . "'>{$d['cuti_sakit']}</td>
                <td class='" . ($d['izin_jam']       > 0 ? 'warning' : 'num-zero') . "'>{$d['izin_jam']}</td>
                <td class='center'>{$d['tugas_luar']}</td>
                <td class='{$alpaClass}'>{$d['alpa']}</td>
                <td class='" . ($d['lembur_jam']      > 0 ? 'num-val' : 'num-zero') . "'>{$d['lembur_jam']}</td>
                <td class='" . ($d['terlambat_jam']   > 0 ? 'warning' : 'num-zero') . "'>{$d['terlambat_jam']}</td>
                <td class='" . ($d['pulang_awal_jam'] > 0 ? 'warning' : 'num-zero') . "'>{$d['pulang_awal_jam']}</td>
            </tr>";
            $no++;
        }
        echo '</table></body></html>';
    };

    return response()->stream($callback, 200, $headers);
})->middleware('web');
