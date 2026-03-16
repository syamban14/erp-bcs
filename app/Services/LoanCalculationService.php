<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanInstallment;
use Carbon\Carbon;

class LoanCalculationService
{
    /**
     * Calculate monthly installment and related amounts
     */
    public function calculateInstallment(float $amount, int $tenor): array
    {
        $interestRate = config('loan.interest_rate_percent', 1.0);
        $interestPerMonth = $amount * ($interestRate / 100);
        $totalInterest = $interestPerMonth * $tenor;
        $totalRepayment = $amount + $totalInterest;
        $monthlyInstallment = $totalRepayment / $tenor;
        $adminFee = config('loan.admin_fee', 25000);
        $disbursementAmount = $amount - $adminFee;
        
        return [
            'amount' => round($amount, 2),
            'tenor_months' => $tenor,
            'interest_rate_percent' => $interestRate,
            'interest_amount_per_month' => round($interestPerMonth, 2),
            'admin_fee' => $adminFee,
            'monthly_installment' => round($monthlyInstallment, 2),
            'total_repayment' => round($totalRepayment, 2),
            'disbursement_amount' => round($disbursementAmount, 2),
        ];
    }
    
    /**
     * Generate installment schedule for a loan
     */
    public function generateInstallmentSchedule(Loan $loan): array
    {
        $installments = [];
        $startDate = Carbon::parse($loan->start_date);
        
        for ($i = 1; $i <= $loan->tenor_months; $i++) {
            $dueDate = $startDate->copy()->addMonths($i - 1);
            
            $installments[] = [
                'loan_id' => $loan->id,
                'installment_number' => $i,
                'amount' => $loan->monthly_installment,
                'due_date' => $dueDate->format('Y-m-d'),
                'status' => 'pending',
            ];
        }
        
        return $installments;
    }
    
    /**
     * Create installment records for a loan
     */
    public function createInstallments(Loan $loan): void
    {
        $schedule = $this->generateInstallmentSchedule($loan);
        
        foreach ($schedule as $installmentData) {
            LoanInstallment::create($installmentData);
        }
    }
}
