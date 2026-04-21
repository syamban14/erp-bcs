<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\SalarySlip;
use Illuminate\Http\Request;

class SalarySlipController extends Controller
{
    /**
     * Get salary slips history
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $payrollId = $user->karyawan?->payroll_id;
        
        $query = SalarySlip::where(function($q) use ($user, $payrollId) {
            $q->where('user_id', $user->id);
            if ($payrollId) {
                $q->orWhere('employee_nik', $payrollId);
            }
        });
        
        // Filter by year
        if ($request->has('year')) {
            $query->whereYear('period', $request->year);
        }
        
        // Filter by month
        if ($request->has('month')) {
            $query->whereMonth('period', $request->month);
        }
        
        $slips = $query->orderBy('period', 'desc')
            ->get()
            ->map(function ($slip) {
                return [
                    'id' => $slip->id,
                    'period' => $slip->period->format('Y-m-d'),
                    'period_string' => $slip->period->format('n - Y'),
                    'net_salary' => $slip->net_salary,
                    'download_url' => $slip->pdf_url,
                ];
            });
        
        return response()->json([
            'status' => 'success',
            'data' => $slips
        ]);
    }
    
    /**
     * Get salary slip detail
     */
    public function show($id, Request $request)
    {
        $user = $request->user();
        $payrollId = $user->karyawan?->payroll_id;
        
        $slip = SalarySlip::with('deductions')
            ->where('id', $id)
            ->where(function($q) use ($user, $payrollId) {
                $q->where('user_id', $user->id);
                if ($payrollId) {
                    $q->orWhere('employee_nik', $payrollId);
                }
            })
            ->first();
        
        if (!$slip) {
            return response()->json([
                'status' => 'error',
                'message' => 'Salary slip not found'
            ], 404);
        }

        // Base static deductions
        $deductions = [
            ['label' => 'Zakat, Infak, Sodaqoh', 'amount' => $slip->zakat],
            ['label' => 'Pajak/PPH.21', 'amount' => $slip->tax],
            ['label' => 'BPJS', 'amount' => $slip->bpjs],
            ['label' => 'Iuran SP-BCS', 'amount' => $slip->union_fee],
            ['label' => 'Alpa/Absen', 'amount' => $slip->absence_deduction, 'meta' => "({$slip->absence_days})"],
            ['label' => 'Koperasi', 'amount' => $slip->cooperative],
            ['label' => 'Angsuran BPR', 'amount' => $slip->bpr_installment],
            ['label' => 'Lain-lain', 'amount' => $slip->other_deduction],
        ];

        // Add dynamic deductions (e.g. Loan Installments)
        foreach ($slip->deductions as $deduction) {
            $meta = null;
            
            // Format meta khusus untuk cicilan kasbon (installment ke berapa dari berapa)
            if ($deduction->type === \App\Models\SalaryDeduction::TYPE_LOAN_INSTALLMENT) {
                $installment = \App\Models\LoanInstallment::where('salary_slip_id', $slip->id)
                    ->where('loan_id', $deduction->reference_id)
                    ->first();
                    
                if ($installment && $installment->loan) {
                    $meta = "({$installment->installment_number}/{$installment->loan->tenor_months})";
                }
            }

            $deductions[] = [
                'label' => $deduction->description ?? $deduction->type,
                'amount' => $deduction->amount,
                'type' => $deduction->type,
                'meta' => $meta,
            ];
        }

        // Hitung total earnings dari field-field di database
        $totalEarnings = $slip->basic_salary
            + $slip->professional_allowance
            + $slip->performance_allowance
            + $slip->position_allowance
            + $slip->meal_allowance
            + $slip->transport_allowance
            + $slip->relocation_allowance
            + $slip->skill_allowance
            + ($slip->other_allowance ?? 0)
            + ($slip->incentive_10th ?? 0)
            + ($slip->communication_allowance ?? 0)
            + ($slip->incentive ?? 0)
            + ($slip->shift_allowance ?? 0)
            + ($slip->overtime_allowance ?? 0)
            + ($slip->khk_allowance ?? 0);

        return response()->json([
            'status' => 'success',
            'data' => [
                'id'           => $slip->id,
                'download_url' => $slip->pdf_url,
                'employee_info' => [
                    'nik'            => $slip->employee_nik,
                    'name'           => $slip->employee_name,
                    'position'       => $slip->employee_position,
                    'division'       => $slip->employee_division,
                    'bank_name'      => $slip->bank_name,
                    'account_number' => $slip->account_number,
                ],
                'period_info' => [
                    'period_string' => $slip->period->format('n - Y'),
                    'work_days'     => 0,
                ],
                'earnings' => [
                    'fixed_allowances' => [
                        ['label' => 'Gaji Pokok',              'amount' => $slip->basic_salary],
                        ['label' => 'Tunj. Profesi/Kontribusi','amount' => $slip->professional_allowance],
                        ['label' => 'Tunj. Prestasi',          'amount' => $slip->performance_allowance],
                        ['label' => 'Tunj. Jabatan',           'amount' => $slip->position_allowance],
                    ],
                    'variable_allowances' => [
                        ['label' => 'Uang Makan',              'amount' => $slip->meal_allowance],
                        ['label' => 'Transport',               'amount' => $slip->transport_allowance],
                        ['label' => 'Tunj. Relokasi',          'amount' => $slip->relocation_allowance],
                        ['label' => 'Tunj. Skill',             'amount' => $slip->skill_allowance],
                        ['label' => 'Tunj. Lain-lain',         'amount' => $slip->other_allowance ?? 0],
                        ['label' => 'Insentif',                'amount' => $slip->incentive ?? 0],
                        ['label' => 'Tunj. Komunikasi',        'amount' => $slip->communication_allowance ?? 0],
                        ['label' => 'Lembur',                  'amount' => $slip->overtime_allowance ?? 0, 'meta' => $slip->overtime_hours ? "{$slip->overtime_hours} jam" : null],
                        ['label' => 'KHK',                     'amount' => $slip->khk_allowance ?? 0, 'meta' => $slip->khk_count ? "{$slip->khk_count} hari" : null],
                    ],
                    'total' => $totalEarnings,
                ],
                'deductions' => $deductions,
                'summary' => [
                    'gross_salary'     => $slip->gross_salary ?: $totalEarnings,
                    'total_deductions' => $slip->total_deductions,
                    'net_salary'       => $slip->net_salary,
                    'salary_in_words'  => $this->terbilang((int)$slip->net_salary),
                    'notes'            => $slip->notes ?: 'Dikirim bersama lampiran dokumen PDF.',
                ],
            ]
        ]);
    }
    /**
     * Export slip gaji ke PDF.
     *
     * GET /api/v1/salaries/{id}/export?format=pdf&token=TOKEN
     * Auth via query ?token= (kompatibel dengan Flutter url_launcher)
     */
    public function export($id, Request $request)
    {
        $user = $request->user();

        $slip = SalarySlip::with(['deductions'])->where('id', $id)->first();

        if (!$slip) {
            return response()->json([
                'meta' => ['code' => 404, 'status' => 'error', 'message' => 'Slip gaji tidak ditemukan.'],
                'data' => null,
            ], 404);
        }

        // Keamanan: karyawan hanya boleh download miliknya sendiri
        $payrollId = $user->karyawan?->payroll_id;
        $isOwner = $slip->user_id == $user->id || ($payrollId && $slip->employee_nik == $payrollId);

        if (!$isOwner) {
            return response()->json([
                'meta' => ['code' => 403, 'status' => 'error', 'message' => 'Anda tidak memiliki akses ke dokumen ini.'],
                'data' => null,
            ], 403);
        }

        // Jika ada file PDF manual yang di-upload, serve langsung
        if ($slip->pdf_path) {
            $filePath = storage_path('app/public/' . $slip->pdf_path);
            if (file_exists($filePath)) {
                return response()->download($filePath, 'Slip_Gaji_' . $slip->period->format('F_Y') . '.pdf');
            }
        }

        // Generate PDF dari data database menggunakan DomPDF
        $totalEarnings = $slip->basic_salary
            + $slip->professional_allowance
            + $slip->performance_allowance
            + $slip->position_allowance
            + $slip->meal_allowance
            + $slip->transport_allowance
            + $slip->relocation_allowance
            + $slip->skill_allowance
            + ($slip->other_allowance ?? 0)
            + ($slip->incentive_10th ?? 0)
            + ($slip->communication_allowance ?? 0)
            + ($slip->incentive ?? 0)
            + ($slip->shift_allowance ?? 0)
            + ($slip->overtime_allowance ?? 0)
            + ($slip->khk_allowance ?? 0);

        // Cek logo perusahaan (gunakan BCSHD / BCS Logistics saja)
        $logoPath = public_path('resources/BCSHD.png');
        $logo = file_exists($logoPath)
            ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
            : null;

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.salary_slip', [
            'slip'          => $slip,
            'totalEarnings' => $totalEarnings,
            'terbilang'     => $this->terbilang((int)$slip->net_salary),
            'logo'          => $logo,
        ])->setPaper('a4', 'portrait');

        $filename = 'Slip_Gaji_' . $slip->employee_nik . '_' . $slip->period->format('M_Y') . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Konversi angka rupiah ke terbilang (Bahasa Indonesia).
     */
    private function terbilang(int $angka): string
    {
        if ($angka <= 0) return 'Nol Rupiah';
        
        $kata = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima',
                 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh',
                 'Sebelas'];
        
        $fn = function (int $n) use (&$fn, $kata): string {
            if ($n < 12)   return $kata[$n];
            if ($n < 20)   return $kata[$n - 10] . ' Belas';
            if ($n < 100)  return $kata[(int)($n / 10)] . ' Puluh ' . $fn($n % 10);
            if ($n < 200)  return 'Seratus ' . $fn($n - 100);
            if ($n < 1000) return $kata[(int)($n / 100)] . ' Ratus ' . $fn($n % 100);
            if ($n < 2000) return 'Seribu ' . $fn($n - 1000);
            if ($n < 1000000)    return $fn((int)($n / 1000)) . ' Ribu ' . $fn($n % 1000);
            if ($n < 1000000000) return $fn((int)($n / 1000000)) . ' Juta ' . $fn($n % 1000000);
            return $fn((int)($n / 1000000000)) . ' Miliar ' . $fn($n % 1000000000);
        };

        return trim($fn($angka)) . ' Rupiah';
    }
}
