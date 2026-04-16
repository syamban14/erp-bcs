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
                        'other_allowance'         => $val('tunj_lain') + $val('rapel_umk_total_lain_lain'),
                        'incentive_10th'          => $val('incentive_10'),
                        'communication_allowance' => $val('tunj_komunikasi'),
                        'incentive'               => $val('fix_incentive') + $val('insentif_lapangan') + $val('kenaikan_upah_total_insentif') + $val('ton_metal_idr') + $val('ton_lokal_idr'),

                        'shift_allowance'    => 0,
                        'overtime_allowance' => $val('umsk_total_overtime') + $val('selisih_umk_grand_total_overtime') + $val('ot_idr'),
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
        $zip = new \ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new \RuntimeException("Tidak dapat membuka file XLSX: {$filePath}");
        }

        // Baca shared strings (teks dalam excel disimpan di sini)
        $sharedStrings = [];
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml !== false) {
            $ss = simplexml_load_string($ssXml);
            foreach ($ss->si as $si) {
                // Gabungkan semua elemen <t> dalam satu <si>
                $text = '';
                foreach ($si->xpath('.//t') as $t) {
                    $text .= (string)$t;
                }
                $sharedStrings[] = $text;
            }
        }

        // Baca sheet pertama
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheetXml === false) {
            throw new \RuntimeException("Sheet pertama tidak ditemukan di dalam file XLSX.");
        }

        $sheet = simplexml_load_string($sheetXml);
        $sheet->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $rows = [];
        foreach ($sheet->xpath('//s:row') as $row) {
            $rowData = [];
            foreach ($row->xpath('s:c') as $cell) {
                $type = (string)($cell['t'] ?? '');
                $rawVal = (string)($cell->v ?? '');

                if ($type === 's') {
                    // Shared string
                    $rowData[] = $sharedStrings[(int)$rawVal] ?? '';
                } elseif ($type === 'b') {
                    $rowData[] = $rawVal === '1';
                } else {
                    // Numerik atau kosong
                    $rowData[] = $rawVal !== '' ? $rawVal : null;
                }
            }
            $rows[] = $rowData;
        }

        return $rows;
    }
}
