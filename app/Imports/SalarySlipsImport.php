<?php

namespace App\Imports;

use App\Models\SalarySlip;
use App\Models\MKaryawan;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * SalarySlipsImport
 *
 * Menggunakan Pure PHP (ZipArchive + SimpleXML) untuk membaca XLSX.
 * Zero dependency — tidak butuh PhpSpreadsheet maupun Maatwebsite.
 * Kompatibel dengan server apapun selama PHP >= 7.4.
 */
class SalarySlipsImport
{
    public function import(string $filePath): int
    {
        $rows = $this->readXlsx($filePath);

        if (empty($rows)) {
            return 0;
        }

        // Baris pertama = header
        $rawHeaders = array_shift($rows);
        $headers = array_map(function ($h) {
            return strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', trim((string)($h ?? ''))));
        }, $rawHeaders);

        $imported = 0;

        foreach ($rows as $rowData) {
            // Tambal jika kolom row lebih sedikit dari header
            $rowData = array_pad($rowData, count($headers), null);
            $row = array_combine($headers, $rowData);

            $payrollId = trim((string)($row['payroll_id'] ?? ''));
            if ($payrollId === '' || !is_numeric($payrollId)) {
                continue;
            }

            $bulan = is_numeric($row['periode_bulan'] ?? null) ? (int)$row['periode_bulan'] : Carbon::now()->month;
            $tahun = is_numeric($row['periode_tahun'] ?? null) ? (int)$row['periode_tahun'] : Carbon::now()->year;
            $period = Carbon::createFromDate($tahun, $bulan, 1)->format('Y-m-d');

            // Ghost Binding
            $karyawan = MKaryawan::where('payroll_id', $payrollId)->first();
            $userId = null;
            if ($karyawan && $karyawan->presensiAccount) {
                $userId = $karyawan->presensiAccount->id;
            }

            $val = function ($key) use ($row) {
                $v = $row[$key] ?? null;
                return is_numeric($v) ? (float)$v : 0;
            };

            try {
                SalarySlip::updateOrCreate(
                    ['employee_nik' => $payrollId, 'period' => $period],
                    [
                        'user_id'      => $userId,
                        'employee_name'     => $row['nama_karyawan'] ?? ($karyawan->nama_karyawan ?? 'Unknown'),
                        'employee_position' => $row['jabatan'] ?? ($karyawan->title ?? '-'),
                        'employee_division' => optional($karyawan->division)->div_name ?? '-',

                        'basic_salary'            => $val('gaji_pokok'),
                        'professional_allowance'  => $val('tunj_profesi_tunj_kontribusi'),
                        'performance_allowance'   => $val('tunj_prestasi'),
                        'position_allowance'      => $val('tunj_jabatan'),

                        'meal_allowance'          => $val('uang_makan_tunj_makan'),
                        'transport_allowance'     => $val('transport_tunj_transport'),
                        'relocation_allowance'    => $val('tunj_relokasi'),
                        'skill_allowance'         => $val('skill_tunj_skill'),
                        'other_allowance'         => $val('rapel_umk_total_lain_lain') > 0 ? $val('rapel_umk_total_lain_lain') : $val('tunj_lain'),
                        'incentive_10th'          => $val('incentive_10'),
                        'communication_allowance' => $val('tunj_komunikasi'),
                        'incentive'               => $val('kenaikan_upah_total_insentif') > 0 ? $val('kenaikan_upah_total_insentif') : ($val('fix_incentive') + $val('insentif_lapangan') + $val('ton_metal_idr') + $val('ton_lokal_idr')),

                        'shift_allowance'    => 0,
                        'overtime_allowance' => $val('selisih_umk_grand_total_overtime') > 0 ? $val('selisih_umk_grand_total_overtime') : ($val('umsk_total_overtime') > 0 ? $val('umsk_total_overtime') : $val('ot_idr')),
                        'overtime_hours'     => $val('ot_hours'),
                        'khk_allowance'      => $val('khk_idr'),
                        'khk_count'          => (int)$val('khk'),

                        'zakat'             => $val('zis_pot_zis'),
                        'tax'               => $val('pajak_pph_21_pot_pajak'),
                        'bpjs'              => $val('bpjs_pot_bpjs'),
                        'union_fee'         => $val('spbcs_pot_spbcs'),
                        'absence_deduction' => $val('absensi_pot_absensi'),
                        'absence_days'      => (int)$val('jumlah_alpa'),
                        'cooperative'       => $val('koperasi_pot_koperasi'),
                        'bpr_installment'   => $val('angsuran_bpr_pot_bpr'),
                        'other_deduction'   => $val('lain_lain_pot_lain_lain'),

                        'gross_salary'     => $val('total_total_bruto'),
                        'total_deductions' => $val('trans_ot_jumlah_pot'),
                        'net_salary'       => $val('makan_katering_total_netto'),

                        'notes' => 'Diimpor otomatis via sistem.',
                    ]
                );
                $imported++;
            } catch (\Exception $e) {
                Log::warning("SalarySlipsImport: Skip NIK={$payrollId} period={$period} - " . $e->getMessage());
            }
        }

        Log::info("SalarySlipsImport: {$imported} record berhasil diproses.");
        return $imported;
    }

    // ─────────────────────────────────────────────────────
    // Pure PHP XLSX Reader (ZipArchive + SimpleXML)
    // Zero dependency - bekerja di semua instalasi PHP >= 7.4
    // ─────────────────────────────────────────────────────

    private function readXlsx(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("File tidak ditemukan: {$filePath}");
        }
        
        $zip = new \ZipArchive();
        $result = $zip->open($filePath);
        if ($result !== true) {
            $errorMap = [
                \ZipArchive::ER_NOZIP  => 'Bukan file ZIP/XLSX yang valid',
                \ZipArchive::ER_OPEN   => 'Tidak bisa membuka file (permission?)',
                \ZipArchive::ER_NOENT  => 'File tidak ditemukan',
                \ZipArchive::ER_INCONS => 'File ZIP rusak/corrupt',
            ];
            $reason = $errorMap[$result] ?? "ZipArchive error code: {$result}";
            throw new \RuntimeException("Gagal membuka XLSX: {$reason} | Path: {$filePath}");
        }

        // Baca shared strings
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

        // Baca sheet pertama
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheetXml === false) {
            throw new \RuntimeException("Sheet pertama tidak ditemukan di dalam file XLSX.");
        }

        // Strip semua namespace secara agresif agar SimpleXML tidak error
        $sheetXml = $this->stripXmlNamespaces($sheetXml);
        $sheet = simplexml_load_string($sheetXml, 'SimpleXMLElement', LIBXML_NOERROR | LIBXML_NOWARNING);

        $rows = [];
        foreach ($sheet->xpath('//row') as $row) {
            $rowData = [];
            foreach ($row->xpath('c') as $cell) {
                // Gunakan atribut 'r' (misal: 'B3') untuk menentukan posisi kolom yang TEPAT
                // karena Excel melewatkan sel kosong, membaca sequential akan misalign!
                $ref      = (string)($cell['r'] ?? '');
                $colIndex = $ref ? $this->columnLetterToIndex($ref) : count($rowData);

                // Pastikan array punya cukup slot (isi null untuk kolom yang dilompati)
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
     * Konversi huruf kolom Excel (A, B, AA, AB...) ke index 0-based.
     * Contoh: A=0, B=1, Z=25, AA=26
     */
    private function columnLetterToIndex(string $cellRef): int
    {
        // Ambil hanya huruf dari referensi seperti 'AB12' → 'AB'
        preg_match('/^([A-Za-z]+)/', $cellRef, $matches);
        $col   = strtoupper($matches[1] ?? 'A');
        $index = 0;
        for ($i = 0; $i < strlen($col); $i++) {
            $index = $index * 26 + (ord($col[$i]) - ord('A') + 1);
        }
        return $index - 1; // Convert ke 0-based
    }

    /**
     * Hapus semua namespace dari XML string secara agresif.
     * Menangani: xmlns declarations, namespace-prefixed attributes (mc:Ignorable),
     * processing instructions, dan prefix elemen (<x:row> → <row>).
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
