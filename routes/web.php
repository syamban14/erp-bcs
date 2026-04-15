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

// ── Export Monthly Recap (SpreadsheetML — format Excel native, tanpa library) ─
Route::get('/admin/monthly-recap/export', function (\Illuminate\Http\Request $request) {
    if (! auth()->check()) {
        abort(403, 'Unauthorized');
    }

    $month  = (int) $request->query('month', now()->month);
    $year   = (int) $request->query('year',  now()->year);
    $unitId = $request->query('unit');

    try {
        $service   = app(\App\Services\RecapService::class);
        $endDate   = \Carbon\Carbon::create($year, $month, 15);
        $startDate = $endDate->copy()->subMonth()->addDay();

        $query = \App\Models\MPresensi::query()->with('officeLocation')->orderBy('name');
        if ($unitId) {
            $query->where('office_location_id', $unitId);
        }
        $employees = $query->get();

        $period   = $startDate->format('d M Y') . ' - ' . $endDate->format('d M Y');
        $printed  = now()->format('d M Y H:i');
        $filename = "recap_presensi_{$month}_{$year}.xls";

        // ── Helper: escape untuk XML ──────────────────────────────────────
        $x = fn($v) => htmlspecialchars((string)$v, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        // ── Helper: buat Cell dengan style ───────────────────────────────
        $cell = function ($val, $type = 'String', $style = 'DataLeft') use ($x) {
            return "<Cell ss:StyleID=\"{$style}\"><Data ss:Type=\"{$type}\">{$x($val)}</Data></Cell>";
        };
        $numCell = function ($val, $style = 'DataCenter') use ($x) {
            $type = is_numeric($val) ? 'Number' : 'String';
            return "<Cell ss:StyleID=\"{$style}\"><Data ss:Type=\"{$type}\">{$x($val)}</Data></Cell>";
        };

        // ── Bangun XML ────────────────────────────────────────────────────
        ob_start();

        // BOM + deklarasi XML (WAJIB ada di baris pertama, tanpa spasi sebelumnya)
        echo "\xEF\xBB\xBF"; // UTF-8 BOM
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\r\n";
        echo '<?mso-application progid="Excel.Sheet"?>' . "\r\n";
        echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:html="http://www.w3.org/TR/REC-html40">' . "\r\n";

        // ── Styles ────────────────────────────────────────────────────────
        echo '<Styles>
  <Style ss:ID="Default"><Font ss:FontName="Calibri" ss:Size="11"/></Style>
  <Style ss:ID="Title">
    <Font ss:FontName="Calibri" ss:Size="14" ss:Bold="1" ss:Color="#1E3A5F"/>
  </Style>
  <Style ss:ID="Subtitle">
    <Font ss:FontName="Calibri" ss:Size="10" ss:Color="#666666"/>
  </Style>
  <Style ss:ID="Header">
    <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#FFFFFF"/>
    <Interior ss:Color="#1E3A5F" ss:Pattern="Solid"/>
    <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
    <Borders>
      <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#AAAAAA"/>
      <Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#AAAAAA"/>
      <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#AAAAAA"/>
      <Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#AAAAAA"/>
    </Borders>
  </Style>
  <Style ss:ID="DataLeft">
    <Font ss:FontName="Calibri" ss:Size="10"/>
    <Borders>
      <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
      <Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
      <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
      <Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
    </Borders>
  </Style>
  <Style ss:ID="DataCenter">
    <Font ss:FontName="Calibri" ss:Size="10"/>
    <Alignment ss:Horizontal="Center"/>
    <Borders>
      <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
      <Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
      <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
      <Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
    </Borders>
  </Style>
  <Style ss:ID="NumVal">
    <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#1E3A5F"/>
    <Alignment ss:Horizontal="Center"/>
    <Borders>
      <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
      <Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
      <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
      <Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
    </Borders>
  </Style>
  <Style ss:ID="NumWarn">
    <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#CC6600"/>
    <Alignment ss:Horizontal="Center"/>
    <Borders>
      <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
      <Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
      <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
      <Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
    </Borders>
  </Style>
  <Style ss:ID="NumDanger">
    <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#CC0000"/>
    <Alignment ss:Horizontal="Center"/>
    <Borders>
      <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
      <Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
      <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
      <Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
    </Borders>
  </Style>
  <Style ss:ID="NumZero">
    <Font ss:FontName="Calibri" ss:Size="10" ss:Color="#BBBBBB"/>
    <Alignment ss:Horizontal="Center"/>
    <Borders>
      <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
      <Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
      <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
      <Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
    </Borders>
  </Style>
</Styles>' . "\r\n";

        // ── Worksheet ─────────────────────────────────────────────────────
        echo '<Worksheet ss:Name="Rekap Presensi">' . "\r\n";
        echo '<Table ss:DefaultColumnWidth="70">' . "\r\n";

        // Column widths
        $colWidths = [30, 160, 120, 60, 60, 70, 70, 70, 60, 60, 70, 60, 70, 70, 80];
        foreach ($colWidths as $w) {
            echo "<Column ss:Width=\"{$w}\"/>\r\n";
        }

        // Judul
        echo "<Row ss:Height=\"20\">"
            . "<Cell ss:MergeAcross=\"14\" ss:StyleID=\"Title\"><Data ss:Type=\"String\">REKAP PRESENSI KARYAWAN</Data></Cell>"
            . "</Row>\r\n";

        echo "<Row>"
            . "<Cell ss:MergeAcross=\"14\" ss:StyleID=\"Subtitle\"><Data ss:Type=\"String\">Periode: {$x($period)}</Data></Cell>"
            . "</Row>\r\n";

        echo "<Row>"
            . "<Cell ss:MergeAcross=\"14\" ss:StyleID=\"Subtitle\"><Data ss:Type=\"String\">Dicetak: {$x($printed)}</Data></Cell>"
            . "</Row>\r\n";

        // Baris kosong
        echo "<Row ss:Height=\"6\"><Cell ss:MergeAcross=\"14\"><Data ss:Type=\"String\"></Data></Cell></Row>\r\n";

        // Header kolom
        $headers = ['No','Nama Karyawan','Unit Kerja','Hari Kerja','Hadir','Durasi (Jam)','Cuti Tahunan','Cuti Spesial','Sakit','Izin (Jam)','Tugas Luar','Alpa','Lembur (Jam)','Telat (Jam)','Pulang Awal (Jam)'];
        echo "<Row ss:Height=\"32\">";
        foreach ($headers as $h) {
            echo "<Cell ss:StyleID=\"Header\"><Data ss:Type=\"String\">{$x($h)}</Data></Cell>";
        }
        echo "</Row>\r\n";

        // Data rows
        $no = 1;
        foreach ($employees as $emp) {
            try {
                $d = $service->getRecapData($emp, $startDate, $endDate);
            } catch (\Throwable $e) {
                // Jika satu karyawan error, isi dengan 0 dan lanjut
                $d = array_fill_keys(['total_hari_kerja','total_kehadiran','durasi_kehadiran','cuti_tahunan','cuti_special','cuti_sakit','izin_jam','tugas_luar','alpa','lembur_jam','terlambat_jam','pulang_awal_jam'], 0);
            }

            $alpaStyle  = $d['alpa'] > 0 ? 'NumDanger' : 'NumZero';
            $hadirStyle = $d['total_kehadiran'] >= $d['total_hari_kerja'] ? 'NumVal' : 'NumWarn';

            echo "<Row ss:Height=\"18\">";
            echo $cell($no,                                'Number', 'DataCenter');
            echo $cell($emp->name,                         'String', 'DataLeft');
            echo $cell($emp->officeLocation->name ?? '-',  'String', 'DataLeft');
            echo $numCell($d['total_hari_kerja'],  'DataCenter');
            echo $numCell($d['total_kehadiran'],   $hadirStyle);
            echo $numCell($d['durasi_kehadiran'],  'DataCenter');
            echo $numCell($d['cuti_tahunan'],  $d['cuti_tahunan']  > 0 ? 'NumVal'    : 'NumZero');
            echo $numCell($d['cuti_special'],  $d['cuti_special']  > 0 ? 'NumVal'    : 'NumZero');
            echo $numCell($d['cuti_sakit'],    $d['cuti_sakit']    > 0 ? 'NumWarn'   : 'NumZero');
            echo $numCell($d['izin_jam'],      $d['izin_jam']      > 0 ? 'NumWarn'   : 'NumZero');
            echo $numCell($d['tugas_luar'],    'DataCenter');
            echo $numCell($d['alpa'],          $alpaStyle);
            echo $numCell($d['lembur_jam'],    $d['lembur_jam']    > 0 ? 'NumVal'    : 'NumZero');
            echo $numCell($d['terlambat_jam'], $d['terlambat_jam'] > 0 ? 'NumWarn'   : 'NumZero');
            echo $numCell($d['pulang_awal_jam'], $d['pulang_awal_jam'] > 0 ? 'NumWarn' : 'NumZero');
            echo "</Row>\r\n";
            $no++;
        }

        echo "</Table>\r\n</Worksheet>\r\n</Workbook>";

        $content = ob_get_clean();

    } catch (\Throwable $e) {
        ob_end_clean();
        abort(500, 'Gagal membuat export: ' . $e->getMessage());
    }

    return response($content, 200, [
        'Content-Type'        => 'application/vnd.ms-excel; charset=UTF-8',
        'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        'Content-Length'      => strlen($content),
        'Pragma'              => 'no-cache',
        'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
        'Expires'             => '0',
    ]);
})->middleware('web');

