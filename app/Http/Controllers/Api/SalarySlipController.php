<?php

namespace App\Http\Controllers\Api;

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
        
        $slip = SalarySlip::where('id', $id)
            ->where('user_id', $user->id)
            ->first();
        
        if (!$slip) {
            return response()->json([
                'status' => 'error',
                'message' => 'Salary slip not found'
            ], 404);
        }
        
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
                'deductions' => [
                    ['label' => 'Zakat, Infak, Sodaqoh', 'amount' => $slip->zakat],
                    ['label' => 'Pajak/PPH.21', 'amount' => $slip->tax],
                    ['label' => 'BPJS', 'amount' => $slip->bpjs],
                    ['label' => 'Iuran SP-BCS', 'amount' => $slip->union_fee],
                    ['label' => 'Alpa/Absen', 'amount' => $slip->absence_deduction, 'meta' => "({$slip->absence_days})"],
                    ['label' => 'Koperasi', 'amount' => $slip->cooperative],
                    ['label' => 'Angsuran BPR', 'amount' => $slip->bpr_installment],
                    ['label' => 'Lain-lain', 'amount' => $slip->other_deduction],
                ],
                'summary' => [
                    'gross_salary' => $slip->gross_salary,
                    'total_deductions' => $slip->total_deductions,
                    'net_salary' => $slip->net_salary,
                    'salary_in_words' => $slip->salary_in_words,
                ],
            ]
        ]);
    }
}
