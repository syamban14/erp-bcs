<?php

namespace App\Imports;

use App\Models\SalarySlip;
use App\Models\MKaryawan;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * SalarySlipsImport
 *
 * Menggunakan PhpSpreadsheet langsung (bukan Maatwebsite interface)
 * untuk kompatibilitas maksimum dengan versi server.
 */
class SalarySlipsImport
{
    /**
     * Import dari path file absolut
     */
    public function import(string $filePath): int
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();

        // Baca semua row sebagai array
        $rows = $sheet->toArray(null, true, true, false);

        if (empty($rows)) {
            return 0;
        }

        // Row pertama adalah header
        $headers = array_map(function($h) {
            // Normalize: lowercase, spasi/simbol jadi underscore (sama dgn WithHeadingRow)
            return strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', trim((string)($h ?? ''))));
        }, array_shift($rows));

        $imported = 0;

        foreach ($rows as $rowData) {
            // Gabungkan header dengan data row
            $row = array_combine($headers, array_pad($rowData, count($headers), null));

            // Abaikan row kosong
            $payrollId = trim((string)($row['payroll_id'] ?? ''));
            if (empty($payrollId)) {
                continue;
            }

            // Tentukan Period
            $bulan = is_numeric($row['periode_bulan'] ?? null) ? (int)$row['periode_bulan'] : Carbon::now()->month;
            $tahun = is_numeric($row['periode_tahun'] ?? null) ? (int)$row['periode_tahun'] : Carbon::now()->year;
            $period = Carbon::createFromDate($tahun, $bulan, 1)->format('Y-m-d');

            // Ghost Binding: cari user di m_presensi via m_karyawan
            $karyawan = MKaryawan::where('payroll_id', $payrollId)->first();
            $userId = null;
            if ($karyawan && $karyawan->presensiAccount) {
                $userId = $karyawan->presensiAccount->id;
            }

            // Helper: safe numeric value
            $val = function($key) use ($row) {
                $v = $row[$key] ?? null;
                return is_numeric($v) ? (float)$v : 0;
            };

            try {
                SalarySlip::updateOrCreate(
                    [
                        'employee_nik' => $payrollId,
                        'period'       => $period,
                    ],
                    [
                        'user_id'      => $userId,

                        // Snapshot Info
                        'employee_name'     => $row['nama_karyawan'] ?? ($karyawan->nama_karyawan ?? 'Unknown'),
                        'employee_position' => $row['jabatan'] ?? ($karyawan->title ?? '-'),
                        'employee_division' => $karyawan->division->div_name ?? '-',

                        // Fixed Allowances (I)
                        'basic_salary'             => $val('gaji_pokok'),
                        'professional_allowance'   => $val('tunj_profesi_tunj_kontribusi'),
                        'performance_allowance'    => $val('tunj_prestasi'),
                        'position_allowance'       => $val('tunj_jabatan'),

                        // Variable Allowances (II)
                        'meal_allowance'           => $val('uang_makan_tunj_makan'),
                        'transport_allowance'      => $val('transport_tunj_transport'),
                        'relocation_allowance'     => $val('tunj_relokasi'),
                        'skill_allowance'          => $val('skill_tunj_skill'),
                        'other_allowance'          => $val('tunj_lain') + $val('rapel_umk_total_lain_lain'),
                        'incentive_10th'           => $val('incentive_10'),
                        'communication_allowance'  => $val('tunj_komunikasi'),
                        'incentive'                => $val('fix_incentive') + $val('insentif_lapangan') + $val('kenaikan_upah_total_insentif') + $val('ton_metal_idr') + $val('ton_lokal_idr'),

                        // Overtime
                        'shift_allowance'    => 0,
                        'overtime_allowance' => $val('umsk_total_overtime') + $val('selisih_umk_grand_total_overtime') + $val('ot_idr'),
                        'overtime_hours'     => $val('ot_hours'),
                        'khk_allowance'      => $val('khk_idr'),
                        'khk_count'          => (int)$val('khk'),

                        // Deductions (III)
                        'zakat'            => $val('zis_pot_zis'),
                        'tax'              => $val('pajak_pph_21_pot_pajak'),
                        'bpjs'             => $val('bpjs_pot_bpjs'),
                        'union_fee'        => $val('spbcs_pot_spbcs'),
                        'absence_deduction'=> $val('absensi_pot_absensi'),
                        'absence_days'     => (int)$val('jumlah_alpa'),
                        'cooperative'      => $val('koperasi_pot_koperasi'),
                        'bpr_installment'  => $val('angsuran_bpr_pot_bpr'),
                        'other_deduction'  => $val('lain_lain_pot_lain_lain'),

                        // Summary
                        'gross_salary'     => $val('total_total_bruto'),
                        'total_deductions' => $val('trans_ot_jumlah_pot'),
                        'net_salary'       => $val('makan_katering_total_netto'),

                        'notes' => 'Diimpor otomatis via sistem.',
                    ]
                );
                $imported++;
            } catch (\Exception $e) {
                Log::warning("SalarySlipsImport: Skip row NIK={$payrollId} - " . $e->getMessage());
            }
        }

        Log::info("SalarySlipsImport: Selesai. {$imported} record berhasil diproses.");
        return $imported;
    }
}
