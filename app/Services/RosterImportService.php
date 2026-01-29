<?php

namespace App\Services;

use App\Models\MPresensi;
use App\Models\ShiftCode;
use App\Models\ShiftSchedule;
use Illuminate\Support\Facades\DB;

class RosterImportService
{
    protected $results = [
        'success' => 0,
        'failed' => 0,
        'errors' => [],
        'warnings' => []
    ];

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

        // Find employee in m_presensi by name (and ID if valid)
        $employee = MPresensi::where(function($query) use ($employeeName, $employeeId) {
            $query->where('name', 'LIKE', "%{$employeeName}%");
            // Hanya tambah kondisi ID jika valid
            if ($employeeId && is_numeric($employeeId)) {
                $query->orWhere('id', (int)$employeeId);
            }
        })->first();

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
}
