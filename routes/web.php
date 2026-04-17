<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminActionController;

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/debug-cost-sales', function () {
    $item = \App\Models\MCostSales::first();
    $cols = \Illuminate\Support\Facades\Schema::connection('pgsql_master')->getColumnListing('m_cost_sales');
    return ['columns' => $cols, 'sample' => $item];
});

Route::get('/debug-rekap', function () {
    // Cari file rekap absensi
    $candidates = glob(base_path('*rekap*')) + glob(base_path('*Rekap*')) + glob(base_path('*REKAP*'));
    if (empty($candidates)) {
        return response()->json(['error' => 'File rekap tidak ditemukan', 'cwd' => base_path()]);
    }
    $filePath = reset($candidates);

    // Baca XLSX dengan Pure PHP
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        return response()->json(['error' => 'Gagal membuka file: ' . $filePath]);
    }

    // Shared strings
    $sharedStrings = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml) {
        $ssXml = preg_replace('/<\?[a-zA-Z].*?\?>/s', '', $ssXml);
        $ssXml = preg_replace('/\s[a-zA-Z][a-zA-Z0-9_]*:[a-zA-Z][a-zA-Z0-9_]*="[^"]*"/', '', $ssXml);
        $ssXml = preg_replace('/\s+xmlns(?::[a-zA-Z0-9_]+)?="[^"]*"/', '', $ssXml);
        $ssXml = preg_replace('/(<\/?)[a-zA-Z][a-zA-Z0-9_]*:/', '$1', $ssXml);
        $ss = simplexml_load_string($ssXml, 'SimpleXMLElement', LIBXML_NOERROR);
        foreach ($ss->si as $si) {
            $t = '';
            foreach ($si->xpath('.//t') as $node) $t .= (string)$node;
            $sharedStrings[] = $t;
        }
    }

    // Sheet 1 - ambil 15 baris pertama
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();

    $sheetXml = preg_replace('/<\?[a-zA-Z].*?\?>/s', '', $sheetXml);
    $sheetXml = preg_replace('/\s[a-zA-Z][a-zA-Z0-9_]*:[a-zA-Z][a-zA-Z0-9_]*="[^"]*"/', '', $sheetXml);
    $sheetXml = preg_replace('/\s+xmlns(?::[a-zA-Z0-9_]+)?="[^"]*"/', '', $sheetXml);
    $sheetXml = preg_replace('/(<\/?)[a-zA-Z][a-zA-Z0-9_]*:/', '$1', $sheetXml);
    $sheet = simplexml_load_string($sheetXml, 'SimpleXMLElement', LIBXML_NOERROR);

    $fn = function($ref) {
        preg_match('/^([A-Za-z]+)/', $ref, $m);
        $col = strtoupper($m[1] ?? 'A');
        $idx = 0;
        for ($i = 0; $i < strlen($col); $i++) $idx = $idx * 26 + (ord($col[$i]) - 64);
        return $idx - 1;
    };

    $rows = [];
    $rowNum = 0;
    foreach ($sheet->xpath('//row') as $row) {
        if ($rowNum++ >= 10) break;
        $r = [];
        foreach ($row->xpath('c') as $c) {
            $ci = $fn((string)($c['r'] ?? ''));
            while (count($r) < $ci) $r[] = null;
            $type = (string)($c['t'] ?? '');
            $v = (string)($c->v ?? '');
            $r[] = $type === 's' ? ($sharedStrings[(int)$v] ?? '') : ($v !== '' ? $v : null);
        }
        $rows[] = $r;
    }

    return response()->json([
        'file' => basename($filePath),
        'total_shared_strings' => count($sharedStrings),
        'rows_preview' => $rows,
    ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
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

        $query = \App\Models\MPresensi::query()->with(['officeLocation', 'karyawan.department', 'karyawan.costSalesInfo'])->orderBy('name');
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
        echo '<Worksheet ss:Name="Sheet1">' . "\r\n";
        echo '<Table>' . "\r\n";

        // Kolom
        echo "<Column ss:Index=\"1\" ss:Width=\"20\"/>\r\n"; // Col A (Blank)
        echo "<Column ss:Index=\"2\" ss:Width=\"80\"/>\r\n"; // ID Karyawan
        echo "<Column ss:Index=\"3\" ss:Width=\"180\"/>\r\n"; // Nama
        echo "<Column ss:Index=\"4\" ss:Width=\"120\"/>\r\n"; // Departemen
        
        $periodRange = $startDate->format('d/m/Y') . ' - ' . $endDate->format('d/m/Y');

        // Judul & Info (Baris 1-11)
        echo "<Row><Cell ss:Index=\"3\" ss:StyleID=\"Title\"><Data ss:Type=\"String\">PT BUANA CENTRA SWAKARSA LOGISTICS</Data></Cell></Row>\r\n";
        echo "<Row><Cell ss:Index=\"2\"><Data ss:Type=\"String\">Tanggal absensi</Data></Cell><Cell><Data ss:Type=\"String\">{$x($periodRange)}</Data></Cell></Row>\r\n";
        echo "<Row><Cell ss:Index=\"2\"><Data ss:Type=\"String\">Lokasi kerja</Data></Cell><Cell><Data ss:Type=\"String\">Semua</Data></Cell></Row>\r\n";
        echo "<Row><Cell ss:Index=\"2\"><Data ss:Type=\"String\">Status kepegawaian</Data></Cell><Cell><Data ss:Type=\"String\">Semua</Data></Cell></Row>\r\n";
        echo "<Row><Cell ss:Index=\"2\"><Data ss:Type=\"String\">Departemen</Data></Cell><Cell><Data ss:Type=\"String\">Semua</Data></Cell></Row>\r\n";
        echo "<Row><Cell ss:Index=\"2\"><Data ss:Type=\"String\">Jabatan</Data></Cell><Cell><Data ss:Type=\"String\">Semua</Data></Cell></Row>\r\n";
        echo "<Row><Cell ss:Index=\"2\"><Data ss:Type=\"String\">Level jabatan</Data></Cell><Cell><Data ss:Type=\"String\">Semua</Data></Cell></Row>\r\n";
        echo "<Row><Cell ss:Index=\"2\"><Data ss:Type=\"String\">COST OF SALES</Data></Cell><Cell><Data ss:Type=\"String\">Semua</Data></Cell></Row>\r\n";
        echo "<Row><Cell ss:Index=\"2\"><Data ss:Type=\"String\">DIVISION</Data></Cell><Cell><Data ss:Type=\"String\">Semua</Data></Cell></Row>\r\n";
        echo "<Row><Cell ss:Index=\"2\"><Data ss:Type=\"String\">DIRECTORATE</Data></Cell><Cell><Data ss:Type=\"String\">Semua</Data></Cell></Row>\r\n";
        echo "<Row><Cell ss:Index=\"2\"><Data ss:Type=\"String\">GRADE</Data></Cell><Cell><Data ss:Type=\"String\">Semua</Data></Cell></Row>\r\n";

        // Header kolom (Baris 12)
        $headers = [
            "ID karyawan", "Nama karyawan", "Nama departemen", "Jumlah hari kerja (jadwal)",
            "Frekuensi hadir sesuai jadwal", "Jam kehadiran", "Frekuensi hadir di luar jadwal",
            "Alpa (hari)", "Alpa tanpa sanksi (hari)", "Tugas masuk di hari libur (hari)",
            "Jam lembur tugas masuk di hari libur", "Jam realisasi lembur tugas masuk di hari libur",
            "Jam lembur SPL", "Jam realisasi lembur", "Jam realisasi lembur (hasil pembulatan)",
            "Nama COST OF SALES", "Pelatihan", "Perjalanan dinas", "Frekuensi pelatihan",
            "Frekuensi perjalanan dinas", "Jam pelatihan", "Jam perjalanan dinas",
            "Jam lembur pelatihan", "Jam lembur perjalanan dinas", " "
        ];
        echo "<Row ss:Height=\"32\">\r\n";
        $colIdx = 2;
        foreach ($headers as $h) {
            echo "<Cell ss:Index=\"{$colIdx}\" ss:StyleID=\"Header\"><Data ss:Type=\"String\">{$x($h)}</Data></Cell>";
            $colIdx++;
        }
        echo "</Row>\r\n";

        // Data rows
        foreach ($employees as $emp) {
            try {
                $d = $service->getRecapData($emp, $startDate, $endDate);
            } catch (\Throwable $e) {
                // Jika satu karyawan error, isi dengan 0 dan lanjut
                $d = array_fill_keys(['total_hari_kerja','total_kehadiran','durasi_kehadiran','cuti_tahunan','cuti_special','cuti_sakit','izin_jam','tugas_luar','alpa','lembur_jam','terlambat_jam','pulang_awal_jam'], 0);
            }

            // Hitung properti gabungan
            $alpaTanpaSanksi = $d['cuti_tahunan'] + $d['cuti_special'] + $d['cuti_sakit'];
            $lemburSPL       = $d['lembur_jam'];
            
            // Aturan penamaan: jika department null, pakai jabatan atau '-'. COST OF SALES sekarang dari m_cost_sales.
            $deptName        = optional(optional($emp->karyawan)->department)->dept_name ?? '-';
            $costOfSales     = optional(optional($emp->karyawan)->costSalesInfo)->cost_sales ?? $emp->officeLocation->name ?? '-';
            $payrollId       = optional($emp->karyawan)->payroll_id ?? $emp->nik ?? '-';

            echo "<Row ss:Height=\"18\">\r\n";
            echo "<Cell ss:Index=\"2\"  ss:StyleID=\"DataLeft\"><Data ss:Type=\"String\">{$x($payrollId)}</Data></Cell>";
            echo "<Cell ss:Index=\"3\"  ss:StyleID=\"DataLeft\"><Data ss:Type=\"String\">{$x(strtoupper($emp->name))}</Data></Cell>";
            echo "<Cell ss:Index=\"4\"  ss:StyleID=\"DataLeft\"><Data ss:Type=\"String\">{$x($deptName)}</Data></Cell>";
            echo "<Cell ss:Index=\"5\"  ss:StyleID=\"DataCenter\"><Data ss:Type=\"Number\">{$x($d['total_hari_kerja'])}</Data></Cell>";
            echo "<Cell ss:Index=\"6\"  ss:StyleID=\"DataCenter\"><Data ss:Type=\"Number\">{$x($d['total_kehadiran'])}</Data></Cell>";
            echo "<Cell ss:Index=\"7\"  ss:StyleID=\"DataCenter\"><Data ss:Type=\"Number\">{$x($d['durasi_kehadiran'])}</Data></Cell>";
            echo "<Cell ss:Index=\"8\"  ss:StyleID=\"DataCenter\"><Data ss:Type=\"Number\">0</Data></Cell>";
            echo "<Cell ss:Index=\"9\"  ss:StyleID=\"DataCenter\"><Data ss:Type=\"Number\">{$x($d['alpa'])}</Data></Cell>";
            echo "<Cell ss:Index=\"10\" ss:StyleID=\"DataCenter\"><Data ss:Type=\"Number\">{$x($alpaTanpaSanksi)}</Data></Cell>";
            echo "<Cell ss:Index=\"11\" ss:StyleID=\"DataCenter\"><Data ss:Type=\"Number\">0</Data></Cell>";
            echo "<Cell ss:Index=\"12\" ss:StyleID=\"DataCenter\"><Data ss:Type=\"Number\">0</Data></Cell>";
            echo "<Cell ss:Index=\"13\" ss:StyleID=\"DataCenter\"><Data ss:Type=\"Number\">0</Data></Cell>";
            echo "<Cell ss:Index=\"14\" ss:StyleID=\"DataCenter\"><Data ss:Type=\"Number\">{$x($lemburSPL)}</Data></Cell>";
            echo "<Cell ss:Index=\"15\" ss:StyleID=\"DataCenter\"><Data ss:Type=\"Number\">{$x($lemburSPL)}</Data></Cell>";
            echo "<Cell ss:Index=\"16\" ss:StyleID=\"DataCenter\"><Data ss:Type=\"Number\">{$x(round($lemburSPL))}</Data></Cell>";
            echo "<Cell ss:Index=\"17\" ss:StyleID=\"DataLeft\"><Data ss:Type=\"String\">{$x($costOfSales)}</Data></Cell>";
            echo "<Cell ss:Index=\"18\" ss:StyleID=\"DataCenter\"><Data ss:Type=\"Number\">0</Data></Cell>";
            echo "<Cell ss:Index=\"19\" ss:StyleID=\"DataCenter\"><Data ss:Type=\"Number\">0</Data></Cell>";
            echo "<Cell ss:Index=\"20\" ss:StyleID=\"DataCenter\"><Data ss:Type=\"Number\">0.0</Data></Cell>";
            echo "<Cell ss:Index=\"21\" ss:StyleID=\"DataCenter\"><Data ss:Type=\"Number\">{$x($d['tugas_luar'])}</Data></Cell>";
            echo "<Cell ss:Index=\"22\" ss:StyleID=\"DataCenter\"><Data ss:Type=\"Number\">0.0</Data></Cell>";
            echo "<Cell ss:Index=\"23\" ss:StyleID=\"DataCenter\"><Data ss:Type=\"Number\">{$x($d['tugas_luar'] * 8)}</Data></Cell>";
            echo "<Cell ss:Index=\"24\" ss:StyleID=\"DataCenter\"><Data ss:Type=\"Number\">0.0</Data></Cell>";
            echo "<Cell ss:Index=\"25\" ss:StyleID=\"DataCenter\"><Data ss:Type=\"Number\">0.0</Data></Cell>";
            echo "<Cell ss:Index=\"26\" ss:StyleID=\"DataCenter\"><Data ss:Type=\"String\"> </Data></Cell>";
            echo "</Row>\r\n";
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

