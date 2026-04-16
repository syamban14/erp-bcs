<?php

namespace App\Services;

use App\Models\MKaryawan;
use App\Models\MPresensi;
use App\Models\ShiftCode;
use App\Models\ShiftSchedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RosterImportService
{
    protected $results = [
        'success' => 0,
        'failed' => 0,
        'errors' => [],
        'warnings' => []
    ];

    public function importFromFile($filePath, $month, $year)
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if ($ext === 'xlsx' || $ext === 'xls') {
            return $this->importFromXlsx($filePath, $month, $year);
        }
        return $this->importFromCsv($filePath, $month, $year);
    }

    public function importFromXlsx($filePath, $month, $year)
    {
        // Pure PHP XLSX reader (ZipArchive + SimpleXML)
        $rows = $this->readXlsx($filePath);

        if (empty($rows)) {
            throw new \Exception('File XLSX kosong atau tidak dapat dibaca.');
        }

        // Skip baris 1 (title 'Upload Roster') dan baris 2 (periode)
        array_shift($rows); // baris 1
        array_shift($rows); // baris 2

        $header = array_shift($rows); // baris 3 = header tanggal
        if (!$header) {
            throw new \Exception('Header tanggal tidak ditemukan di baris ke-3 file XLSX.');
        }

        $dates = $this->parseDateHeader($header, $month, $year);

        $rowNumber = 3;
        foreach ($rows as $row) {
            $rowNumber++;
            $this->processEmployeeRow($row, $dates, $rowNumber);
        }

        return $this->results;
    }

    public function importFromCsv($filePath, $month, $year)
    {
        if (!file_exists($filePath)) {
            throw new \Exception("File tidak ditemukan: {$filePath}");
        }

        $file = fopen($filePath, 'r');
        if (!$file) {
            throw new \Exception("Gagal membuka file CSV");
        }

        // Skip 2 baris pertama (title rows)
        // Baris 1: "Upload Roster"
        // Baris 2: ";;Dec-25;;...;;Jan-26;;"
        fgetcsv($file, 0, ';'); // Skip baris 1
        fgetcsv($file, 0, ';'); // Skip baris 2

        // Read header row (baris 3: ID Karyawan, Nama Karyawan, 16, 17, ...)
        $header = fgetcsv($file, 0, ';'); // Gunakan semicolon sebagai delimiter
        if (!$header) {
            fclose($file);
            throw new \Exception("File CSV kosong atau format tidak valid");
        }

        // Parse tanggal dari header (kolom 2→ adalah tanggal)
        $dates = $this->parseDateHeader($header, $month, $year);

        // Process each employee row
        $rowNumber = 3; // Mulai dari baris 4 (setelah 2 title + 1 header)
        while (($row = fgetcsv($file, 0, ';')) !== false) { // Gunakan semicolon
            $rowNumber++;
            $this->processEmployeeRow($row, $dates, $rowNumber);
        }

        fclose($file);
        return $this->results;
    }

    private function parseDateHeader($header, $month, $year)
    {
        $dates = [];
        
        // Header format: ID Karyawan, Nama Karyawan, 16, 17, 18, ..., 31, 01, 02, ..., 15
        // Periode: 16 bulan ini → 15 bulan berikutnya
        // Contoh: 16 Des 2025 - 15 Jan 2026
        
        // Skip kolom 0-1 (ID, Nama), mulai dari kolom 2
        for ($i = 2; $i < count($header); $i++) {
            $day = trim($header[$i]);
            
            // Check if it's a valid day number
            if (is_numeric($day) && $day >= 1 && $day <= 31) {
                $dayNum = (int)$day;
                
                // Tanggal 16-31: bulan ini
                if ($dayNum >= 16) {
                    $dates[$i] = sprintf('%04d-%02d-%02d', $year, $month, $dayNum);
                }
                // Tanggal 01-15: bulan berikutnya
                else if ($dayNum >= 1 && $dayNum <= 15) {
                    // Calculate next month
                    $nextMonth = $month + 1;
                    $nextYear = $year;
                    
                    if ($nextMonth > 12) {
                        $nextMonth = 1;
                        $nextYear++;
                    }
                    
                    $dates[$i] = sprintf('%04d-%02d-%02d', $nextYear, $nextMonth, $dayNum);
                }
            }
        }

        if (empty($dates)) {
            throw new \Exception("Tidak ada tanggal valid ditemukan di header CSV");
        }

        return $dates;
    }

    private function processEmployeeRow($row, $dates, $rowNumber)
    {
        // Skip empty rows
        if (empty(array_filter($row))) {
            return;
        }

        $employeeId = $row[0] ?? null;
        $employeeName = $row[1] ?? null;

        if (!$employeeName) {
            $this->results['warnings'][] = "Baris {$rowNumber}: Nama karyawan kosong, dilewati";
            return;
        }

        // Clean employee ID: hapus titik dan spasi
        // Contoh: "22.043.972" -> "22043972"
        if ($employeeId) {
            $employeeId = str_replace(['.', ' ', ','], '', $employeeId);
            // Jika setelah dibersihkan bukan numeric, set null
            if (!is_numeric($employeeId)) {
                $employeeId = null;
            }
        }

        // === STRATEGI PENCOCOKAN BERLAPIS ===
        $employee = null;
        $normName = $this->normalizeName($employeeName);

        // 1. Exact name match (case insensitive)
        $employee = MPresensi::whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim($employeeName))])->first();

        // 2. Normalized match (hapus spasi ganda, lowercase)
        if (!$employee && $normName) {
            $employee = MPresensi::whereRaw('LOWER(REGEXP_REPLACE(TRIM(name), \' +\', \' \', \'g\')) = ?', [$normName])->first();
        }

        // 3. Via Payroll ID (kol 0) → m_karyawan → m_presensi
        if (!$employee && $employeeId && is_numeric($employeeId)) {
            $karyawan = \App\Models\MKaryawan::where('payroll_id', $employeeId)->first();
            if ($karyawan && $karyawan->presensiAccount) {
                $employee = $karyawan->presensiAccount;
            }
        }

        // 4. Fallback: LIKE %name% (fuzzy - least reliable)
        if (!$employee) {
            $employee = MPresensi::where('name', 'ILIKE', "%{$normName}%")->first();
        }

        if (!$employee) {
            $this->results['errors'][] = "Baris {$rowNumber}: Karyawan tidak ditemukan - {$employeeName}";
            $this->results['failed']++;
            return;
        }

        // Process each date column
        $imported = 0;
        foreach ($dates as $colIndex => $date) {
            $shiftCode = $row[$colIndex] ?? null;
            
            // Skip empty cells
            if (!$shiftCode || trim($shiftCode) === '') {
                continue;
            }

            $shiftCode = strtoupper(trim($shiftCode));

            // Verify shift code exists
            if (!ShiftCode::where('code', $shiftCode)->exists()) {
                $this->results['warnings'][] = "Baris {$rowNumber}, Tanggal {$date}: Kode shift tidak valid - {$shiftCode}";
                continue;
            }

            // Insert/update schedule
            try {
                ShiftSchedule::updateOrCreate(
                    [
                        'user_id' => $employee->id,
                        'date' => $date,
                    ],
                    [
                        'shift_code' => $shiftCode
                    ]
                );
                $imported++;
            } catch (\Exception $e) {
                $this->results['warnings'][] = "Baris {$rowNumber}, Tanggal {$date}: Gagal simpan - " . $e->getMessage();
            }
        }

        if ($imported > 0) {
            $this->results['success'] += $imported;
        }
    }

    public function getResults()
    {
        return $this->results;
    }

    // ─────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────

    private function normalizeName(string $name): string
    {
        // Lowercase, trim, hapus spasi ganda
        return strtolower(preg_replace('/\s+/', ' ', trim($name)));
    }

    private function readXlsx(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("File tidak ditemukan: {$filePath}");
        }

        $zip = new \ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new \RuntimeException('Gagal membuka file XLSX. Pastikan file tidak rusak.');
        }

        // Shared strings
        $sharedStrings = [];
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml !== false) {
            $ssXml = $this->stripXmlNamespaces($ssXml);
            $ss = simplexml_load_string($ssXml, 'SimpleXMLElement', LIBXML_NOERROR | LIBXML_NOWARNING);
            if ($ss) {
                foreach ($ss->si as $si) {
                    $text = '';
                    foreach ($si->xpath('.//t') as $t) {
                        $text .= (string)$t;
                    }
                    $sharedStrings[] = $text;
                }
            }
        }

        // Sheet 1
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheetXml === false) {
            throw new \RuntimeException('Sheet pertama tidak ditemukan di dalam XLSX.');
        }

        $sheetXml = $this->stripXmlNamespaces($sheetXml);
        $sheet = simplexml_load_string($sheetXml, 'SimpleXMLElement', LIBXML_NOERROR | LIBXML_NOWARNING);

        $rows = [];
        foreach ($sheet->xpath('//row') as $row) {
            $rowData = [];
            foreach ($row->xpath('c') as $cell) {
                // Gunakan atribut 'r' untuk posisi kolom yang TEPAT
                // Excel melewatkan sel kosong — sequential reading akan misalign!
                $ref      = (string)($cell['r'] ?? '');
                $colIndex = $ref ? $this->columnLetterToIndex($ref) : count($rowData);

                while (count($rowData) < $colIndex) {
                    $rowData[] = null;
                }

                $type   = (string)($cell['t'] ?? '');
                $rawVal = (string)($cell->v ?? '');

                if ($type === 's') {
                    $value = $sharedStrings[(int)$rawVal] ?? '';
                } elseif ($type === 'b') {
                    $value = $rawVal === '1';
                } else {
                    $value = $rawVal !== '' ? $rawVal : null;
                }

                if ($colIndex < count($rowData)) {
                    $rowData[$colIndex] = $value;
                } else {
                    $rowData[] = $value;
                }
            }
            if (!empty(array_filter($rowData, fn($v) => $v !== null && $v !== ''))) {
                $rows[] = $rowData;
            }
        }

        return $rows;
    }

    /**
     * Konversi huruf kolom Excel (A, B, AA...) ke index 0-based.
     */
    private function columnLetterToIndex(string $cellRef): int
    {
        preg_match('/^([A-Za-z]+)/', $cellRef, $matches);
        $col   = strtoupper($matches[1] ?? 'A');
        $index = 0;
        for ($i = 0; $i < strlen($col); $i++) {
            $index = $index * 26 + (ord($col[$i]) - ord('A') + 1);
        }
        return $index - 1;
    }

    /**
     * Hapus semua namespace dari XML string secara agresif.
     */
    private function stripXmlNamespaces(string $xml): string
    {
        // 1. Hapus processing instructions
        $xml = preg_replace('/<\?[a-zA-Z].*?\?>/s', '', $xml);
        // 2. Hapus atribut dengan namespace prefix (mc:Ignorable="...", r:id="...")
        $xml = preg_replace('/\s[a-zA-Z][a-zA-Z0-9_]*:[a-zA-Z][a-zA-Z0-9_]*="[^"]*"/', '', $xml);
        // 3. Hapus deklarasi namespace
        $xml = preg_replace('/\s+xmlns(?::[a-zA-Z0-9_]+)?="[^"]*"/', '', $xml);
        // 4. Hapus prefix dari nama elemen
        $xml = preg_replace('/(<\/?)[a-zA-Z][a-zA-Z0-9_]*:/', '$1', $xml);
        return $xml;
    }
}
