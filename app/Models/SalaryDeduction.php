<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryDeduction extends Model
{
    protected $connection = 'pgsql';
    
    protected $fillable = [
        'salary_slip_id',
        'type',
        'description',
        'amount',
        'reference_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /**
     * Deduction types
     */
    const TYPE_LOAN_INSTALLMENT = 'LOAN_INSTALLMENT';
    const TYPE_TAX = 'TAX';
    const TYPE_BPJS = 'BPJS';
    const TYPE_OTHER = 'OTHER';

    /**
     * Get the salary slip that owns this deduction
     */
    public function salarySlip(): BelongsTo
    {
        return $this->belongsTo(SalarySlip::class);
    }

    /**
     * Get the loan if this is a loan deduction
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class, 'reference_id');
    }

    /**
     * Scope for loan deductions
     */
    public function scopeLoanDeductions($query)
    {
        return $query->where('type', self::TYPE_LOAN_INSTALLMENT);
    }

    /**
     * Get formatted amount
     */
    public function getFormattedAmountAttribute(): string
    {
        return 'Rp ' . number_format($this->amount, 0, ',', '.');
    }
}
