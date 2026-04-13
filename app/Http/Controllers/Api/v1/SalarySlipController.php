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
        
        $query = SalarySlip::where('user_id', $user->id);
        
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
        
        $slip = SalarySlip::with('deductions') // Eager load deductions
            ->where('id', $id)
            ->where('user_id', $user->id)
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

        // Karena sistem kini menggunakan sistem upload PDF penuh, komponen nominal usang tidak lagi dilampirkan agar Mobile App tidak menampilkan angka 0.
        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $slip->id,
                'download_url' => $slip->pdf_url,
                'employee_info' => [
                    'nik' => $slip->employee_nik,
                    'name' => $slip->employee_name,
                    'position' => $slip->employee_position,
                    'division' => $slip->employee_division,
                    'bank_name' => $slip->bank_name,
                    'account_number' => $slip->account_number,
                ],
                'period_info' => [
                    'period_string' => $slip->period->format('n - Y'),
                    'work_days' => 0,
                ],
                'earnings' => [
                    'fixed_allowances' => [],
                    'variable_allowances' => [],
                ],
                'deductions' => [],
                'summary' => [
                    'gross_salary' => 0,
                    'total_deductions' => 0,
                    'net_salary' => 0,
                    'salary_in_words' => '-',
                    'notes' => $slip->notes ?: 'Terkirim bersama lampiran dokumen PDF.',
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

        // Ambil slip + pastikan milik user yang login
        $slip = SalarySlip::with(['deductions' => function ($q) {
            $q->with('loan');
        }])->where('id', $id)->first();

        if (!$slip) {
            return response()->json([
                'meta' => ['code' => 404, 'status' => 'error', 'message' => 'Slip gaji tidak ditemukan.'],
                'data' => null,
            ], 404);
        }

        // Keamanan: karyawan hanya boleh download miliknya sendiri
        if ($slip->user_id != $user->id) {
            return response()->json([
                'meta' => ['code' => 403, 'status' => 'error', 'message' => 'Anda tidak memiliki akses ke dokumen ini.'],
                'data' => null,
            ], 403);
        }

        // Jika tidak ada file spesifik hasil upload dari HR, batalkan
        if (!$slip->pdf_path) {
            return response()->json([
                'meta' => ['code' => 404, 'status' => 'error', 'message' => 'File slip gaji belum diunggah.'],
                'data' => null,
            ], 404);
        }

        $filePath = storage_path('app/public/' . $slip->pdf_path);
        
        if (!file_exists($filePath)) {
            return response()->json([
                'meta' => ['code' => 404, 'status' => 'error', 'message' => 'File tidak ditemukan di server.'],
                'data' => null,
            ], 404);
        }

        $filename = 'Slip_Gaji_' . $slip->period->format('F_Y') . '.pdf';
        
        return response()->download($filePath, $filename);
    }
}
