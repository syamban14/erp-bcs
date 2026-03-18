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

        // Calculate totals dynamically if needed, but rely on DB columns for now to match UI
        // Or use the new accessor if we want real-time calculation
        $grossSalary = $slip->gross_salary;
        // Use the accessor that includes dynamic deductions
        $totalDeductions = $slip->total_deductions_with_dynamic; 
        $netSalary = $slip->net_salary_after_deductions;
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $slip->id,
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
                    'work_days' => $slip->work_days,
                ],
                'earnings' => [
                    'fixed_allowances' => [
                        ['label' => 'Upah Pokok', 'amount' => $slip->basic_salary],
                        ['label' => 'Tunj. Kontribusi Profesi', 'amount' => $slip->professional_allowance],
                        ['label' => 'Tunj. Prestasi', 'amount' => $slip->performance_allowance],
                        ['label' => 'Tunj. Jabatan', 'amount' => $slip->position_allowance],
                    ],
                    'variable_allowances' => [
                        ['label' => 'Makan', 'amount' => $slip->meal_allowance],
                        ['label' => 'Transport', 'amount' => $slip->transport_allowance],
                        ['label' => 'Tunj. Relokasi', 'amount' => $slip->relocation_allowance],
                        ['label' => 'Tunj. Skill', 'amount' => $slip->skill_allowance],
                        ['label' => 'Tunj. Lain-lain', 'amount' => $slip->other_allowance],
                        ['label' => 'Incentive tgl 10', 'amount' => $slip->incentive_10th],
                        ['label' => 'Tunj. Komunikasi', 'amount' => $slip->communication_allowance],
                        ['label' => 'Insentif', 'amount' => $slip->incentive],
                        ['label' => 'Shift', 'amount' => $slip->shift_allowance, 'meta' => "({$slip->shift_count})"],
                        ['label' => 'Lembur', 'amount' => $slip->overtime_allowance, 'meta' => "({$slip->overtime_hours})"],
                        ['label' => 'Khk', 'amount' => $slip->khk_allowance, 'meta' => "({$slip->khk_count})"],
                    ],
                ],
                'deductions' => $deductions, // Use merged deductions
                'summary' => [
                    'gross_salary' => $grossSalary,
                    'total_deductions' => $totalDeductions,
                    'net_salary' => $netSalary,
                    'salary_in_words' => $slip->salary_in_words,
                ],
            ]
        ]);
    }
}
