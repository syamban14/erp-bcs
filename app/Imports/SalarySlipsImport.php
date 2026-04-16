<?php

namespace App\Imports;

use App\Models\SalarySlip;
use App\Models\MKaryawan;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\Log;

class SalarySlipsImport implements ToCollection, WithHeadingRow
{
    /**
    * @param Collection $rows
    */
    public function collection(Collection $rows)
    {
        $imported = 0;
        
        foreach ($rows as $row) {
            // Abaikan row kosong atau tidak ada payroll_id
            if (!isset($row['payroll_id']) || empty($row['payroll_id'])) {
                continue;
            }

            // Sanitasi Payroll ID (dari misal float "1611.3175" jadi string persis)
            $payrollId = (string) $row['payroll_id'];

            // Tentukan Period (tanggal 1 bulan & tahun itu)
            $bulan = $row['periode_bulan'] ?? Carbon::now()->month;
            $tahun = $row['periode_tahun'] ?? Carbon::now()->year;
            $period = Carbon::createFromDate($tahun, $bulan, 1)->format('Y-m-d');

            // Cari keterkaitan Ghost Record ke Mobile Presensi ID
            $karyawan = MKaryawan::where('payroll_id', $payrollId)->first();
            $userId = null;
            if ($karyawan && $karyawan->presensiAccount) {
                $userId = $karyawan->presensiAccount->id;
            }

            // Helper function to safely get numeric value
            $val = function($key) use ($row) {
                // Return 0 if not exists or empty, cast to float
                return isset($row[$key]) && is_numeric($row[$key]) ? (float)$row[$key] : 0;
            };

            // Mapping berdasarkan Header Array Excel orisinal
            // Catatan: WithHeadingRow mengubah spasi jadi underscore dan menurunkan huruf jadi lowercase (slugify)
            
            // Note: Header keys from your specific Epayslip.xlsx (lowercase due to WithHeadingRow)
            // 'gaji_pokok', 'tunj_profesi_tunj_kontribusi', 'tunj_prestasi', 'tunj_jabatan'
            // 'uang_makan_tunj_makan', 'transport_tunj_transport', 'tunj_relokasi'
            // 'skill_tunj_skill', 'tunj_lain', 'incentive_10', 'rapel_umk_total_lain_lain'
            // 'tunj_komunikasi', 'fix_incentive', 'kenaikan_upah_total_insentif'
            // 'ot_hours', 'umsk_total_overtime' (or 'selisih_umk_grand_total_overtime')
            // 'khk', 'khk_idr', 'total_total_bruto'
            // 'zis_pot_zis', 'pajak_pph_21_pot_pajak', 'bpjs_pot_bpjs', 'spbcs_pot_spbcs'
            // 'absensi_pot_absensi', 'koperasi_pot_koperasi', 'angsuran_bpr_pot_bpr', 'lain_lain_pot_lain_lain'
            // 'trans_ot_jumlah_pot', 'makan_katering_total_netto', 'jumlah_alpa'

            SalarySlip::updateOrCreate(
                [
                    'employee_nik' => $payrollId,
                    'period' => $period,
                ],
                [
                    'user_id' => $userId, // Can be NULL (Ghost Bind)
                    
                    // Employee Info Snapshot
                    'employee_name' => $row['nama_karyawan'] ?? ($karyawan->nama_karyawan ?? 'Unknown'),
                    'employee_position' => $row['jabatan'] ?? ($karyawan->title ?? '-'),
                    'employee_division' => $karyawan->division->div_name ?? '-',
                    
                    // Fixed Allowances (I)
                    'basic_salary' => $val('gaji_pokok'),
                    'professional_allowance' => $val('tunj_profesi_tunj_kontribusi'),
                    'performance_allowance' => $val('tunj_prestasi'),
                    'position_allowance' => $val('tunj_jabatan'),
                    
                    // Variable Allowances (II)
                    'meal_allowance' => $val('uang_makan_tunj_makan'),
                    'transport_allowance' => $val('transport_tunj_transport'),
                    'relocation_allowance' => $val('tunj_relokasi'),
                    'skill_allowance' => $val('skill_tunj_skill'),
                    
                    'other_allowance' => $val('tunj_lain') + $val('rapel_umk_total_lain_lain'),
                    'incentive_10th' => $val('incentive_10'),
                    'communication_allowance' => $val('tunj_komunikasi'),
                    
                    // Menggabungkan insentif-insentif custom ke dalam general incentive
                    'incentive' => $val('fix_incentive') + $val('insentif_lapangan') + $val('kenaikan_upah_total_insentif') + $val('ton_metal_idr') + $val('ton_lokal_idr'),
                    
                    // Overtime & Shifts
                    'shift_allowance' => 0, // Not explicitly in excel
                    'overtime_allowance' => $val('umsk_total_overtime') + $val('selisih_umk_grand_total_overtime') + $val('ot_idr'),
                    'overtime_hours' => $val('ot_hours'),
                    'khk_allowance' => $val('khk_idr'),
                    'khk_count' => $val('khk'),
                    
                    // Deductions (III)
                    'zakat' => $val('zis_pot_zis'),
                    'tax' => $val('pajak_pph_21_pot_pajak'),
                    'bpjs' => $val('bpjs_pot_bpjs'),
                    'union_fee' => $val('spbcs_pot_spbcs'),
                    'absence_deduction' => $val('absensi_pot_absensi'),
                    'absence_days' => $val('jumlah_alpa'),
                    'cooperative' => $val('koperasi_pot_koperasi'),
                    'bpr_installment' => $val('angsuran_bpr_pot_bpr'),
                    'other_deduction' => $val('lain_lain_pot_lain_lain'),
                    
                    // Summaries
                    'gross_salary' => $val('total_total_bruto'),
                    'total_deductions' => $val('trans_ot_jumlah_pot'),
                    'net_salary' => $val('makan_katering_total_netto'),
                    
                    'notes' => 'Telah diimpor dan disinkronkan otomatis via sistem.',
                ]
            );
            $imported++;
        }
        
        Log::info("SalarySlipsImport: Successfully processed {$imported} records.");
    }
}
