<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\SalarySlip;
use App\Models\SalaryDeduction;
use Carbon\Carbon;

class LoanDeductionService
{
    /**
     * Process loan deductions for a salary slip
     * 
     * This method finds active loans for the user and creates deductions
     * for any pending installments that are due.
     */
    public function processLoanDeductions(SalarySlip $salarySlip): void
    {
        // Check if auto-deduct is enabled
        if (!config('loan.auto_deduct', true)) {
            return;
        }

        // Get user ID from salary slip
        $userId = $salarySlip->user_id;

        // Find active loans for this user
        $activeLoans = Loan::forUser($userId)
            ->where('status', 'active')
            ->get();

        foreach ($activeLoans as $loan) {
            // Get the next pending installment
            $installment = $loan->installments()
                ->where('status', 'pending')
                ->where('due_date', '<=', Carbon::parse($salarySlip->period)->endOfMonth())
                ->orderBy('installment_number')
                ->first();

            if (!$installment) {
                continue; // No pending installment for this period
            }

            // Create deduction record
            $deduction = SalaryDeduction::create([
                'salary_slip_id' => $salarySlip->id,
                'type' => SalaryDeduction::TYPE_LOAN_INSTALLMENT,
                'description' => "Potongan Kasbon - Cicilan ke-{$installment->installment_number} dari {$loan->tenor}",
                'amount' => $installment->amount,
                'reference_id' => $loan->id,
            ]);

            // Mark installment as paid
            $installment->update([
                'status' => 'paid',
                'paid_at' => now(),
                'salary_slip_id' => $salarySlip->id,
            ]);

            // Update loan remaining amount
            $loan->remaining_amount -= $installment->amount;

            // Check if loan is fully paid
            $paidCount = $loan->installments()->where('status', 'paid')->count();
            if ($paidCount >= $loan->tenor) {
                $loan->status = 'paid_off';
            }

            $loan->save();
        }
    }

    /**
     * Get deductions preview for a user in a specific period
     * 
     * This is useful for previewing deductions before generating salary slip
     */
    public function getDeductionsForUser(int $userId, Carbon $period): array
    {
        $deductions = [];

        // Find active loans
        $activeLoans = Loan::forUser($userId)
            ->where('status', 'active')
            ->get();

        foreach ($activeLoans as $loan) {
            // Get pending installment for this period
            $installment = $loan->installments()
                ->where('status', 'pending')
                ->where('due_date', '<=', $period->endOfMonth())
                ->orderBy('installment_number')
                ->first();

            if ($installment) {
                $deductions[] = [
                    'type' => 'LOAN_INSTALLMENT',
                    'description' => "Potongan Kasbon - Cicilan ke-{$installment->installment_number}",
                    'amount' => $installment->amount,
                    'loan_id' => $loan->id,
                    'installment_id' => $installment->id,
                ];
            }
        }

        return $deductions;
    }

    /**
     * Reverse a deduction (if salary slip is deleted or needs correction)
     * 
     * This will mark the installment as pending again and restore loan balance
     */
    public function reverseDeduction(SalaryDeduction $deduction): void
    {
        if ($deduction->type !== SalaryDeduction::TYPE_LOAN_INSTALLMENT) {
            return; // Only reverse loan deductions
        }

        // Find the loan
        $loan = Loan::find($deduction->reference_id);
        if (!$loan) {
            return;
        }

        // Find the installment that was paid via this deduction
        $installment = $loan->installments()
            ->where('salary_slip_id', $deduction->salary_slip_id)
            ->where('status', 'paid')
            ->first();

        if ($installment) {
            // Mark installment as pending again
            $installment->update([
                'status' => 'pending',
                'paid_at' => null,
                'salary_slip_id' => null,
            ]);

            // Restore loan remaining amount
            $loan->remaining_amount += $installment->amount;

            // If loan was paid_off, revert to active
            if ($loan->status === 'paid_off') {
                $loan->status = 'active';
            }

            $loan->save();
        }

        // Delete the deduction record
        $deduction->delete();
    }

    /**
     * Get total deductions amount for a user in a period
     */
    public function getTotalDeductionsAmount(int $userId, Carbon $period): float
    {
        $deductions = $this->getDeductionsForUser($userId, $period);
        return array_sum(array_column($deductions, 'amount'));
    }
}
