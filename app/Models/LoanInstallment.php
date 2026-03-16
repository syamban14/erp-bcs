<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class LoanInstallment extends Model
{
    protected $connection = 'pgsql'; // presensi_db
    
    protected $fillable = [
        'loan_id',
        'installment_number',
        'amount',
        'due_date',
        'paid_date',
        'status',
        'salary_slip_id',
    ];
    
    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'paid_date' => 'date',
    ];
    
    /**
     * Relation to loan
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
    
    /**
     * Relation to salary slip
     */
    public function salarySlip(): BelongsTo
    {
        return $this->belongsTo(SalarySlip::class);
    }
    
    /**
     * Scope: Pending installments
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
    
    /**
     * Scope: Paid installments
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }
    
    /**
     * Scope: Overdue installments
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', 'pending')
            ->where('due_date', '<', Carbon::today());
    }
    
    /**
     * Accessor: Check if overdue
     */
    public function getIsOverdueAttribute(): bool
    {
        return $this->status === 'pending' && $this->due_date < Carbon::today();
    }
    
    /**
     * Accessor: Days overdue
     */
    public function getDaysOverdueAttribute(): int
    {
        if (!$this->is_overdue) {
            return 0;
        }
        
        return Carbon::today()->diffInDays($this->due_date);
    }
}
