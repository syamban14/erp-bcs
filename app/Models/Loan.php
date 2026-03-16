<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Loan extends Model
{
    protected $connection = 'pgsql'; // presensi_db
    
    protected $fillable = [
        'user_id',
        'amount',
        'tenor_months',
        'interest_rate_percent',
        'interest_amount_per_month',
        'admin_fee',
        'monthly_installment',
        'total_repayment',
        'disbursement_amount',
        'remaining_amount',
        'reason',
        'reason_detail',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'start_date',
        'end_date',
        'bank_account_number',
        'bank_name',
        'disbursement_date',
    ];
    
    protected $casts = [
        'amount' => 'decimal:2',
        'interest_rate_percent' => 'decimal:2',
        'interest_amount_per_month' => 'decimal:2',
        'admin_fee' => 'decimal:2',
        'monthly_installment' => 'decimal:2',
        'total_repayment' => 'decimal:2',
        'disbursement_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'start_date' => 'date',
        'end_date' => 'date',
        'disbursement_date' => 'date',
    ];
    
    /**
     * Relation to user (MPresensi)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(MPresensi::class, 'user_id');
    }
    
    /**
     * Relation to approver
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(MPresensi::class, 'approved_by');
    }
    
    /**
     * Relation to installments
     */
    public function installments(): HasMany
    {
        return $this->hasMany(LoanInstallment::class);
    }
    
    /**
     * Scope: Active loans
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
    
    /**
     * Scope: Pending approval
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending_approval');
    }
    
    /**
     * Scope: For specific user
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
    
    /**
     * Accessor: Get next unpaid installment
     */
    public function getNextInstallmentAttribute()
    {
        return $this->installments()
            ->where('status', 'pending')
            ->orderBy('installment_number')
            ->first();
    }
    
    /**
     * Accessor: Count paid installments
     */
    public function getPaidInstallmentsCountAttribute()
    {
        return $this->installments()->where('status', 'paid')->count();
    }
    
    /**
     * Accessor: Count remaining installments
     */
    public function getRemainingInstallmentsCountAttribute()
    {
        return $this->installments()->where('status', 'pending')->count();
    }
    
    /**
     * Static: Calculate monthly installment
     */
    public static function calculateInstallment(float $amount, int $tenor, float $interestRate = 1.0): array
    {
        $interestPerMonth = $amount * ($interestRate / 100);
        $totalInterest = $interestPerMonth * $tenor;
        $totalRepayment = $amount + $totalInterest;
        $monthlyInstallment = $totalRepayment / $tenor;
        $adminFee = config('loan.admin_fee', 25000);
        $disbursementAmount = $amount - $adminFee;
        
        return [
            'amount' => $amount,
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
     * Static: Get max loan limit for user (based on salary)
     */
    public static function getMaxLimit(int $userId): float
    {
        $user = MPresensi::find($userId);
        if (!$user) {
            return 0;
        }
        
        // Get employee data from master_db
        $employee = MKaryawan::on('pgsql_master')
            ->where('id', $user->karyawan_id)
            ->first();
        
        if (!$employee || !$employee->gaji_pokok) {
            return 0;
        }
        
        $multiplier = config('loan.max_limit_multiplier', 3);
        return $employee->gaji_pokok * $multiplier;
    }
    
    /**
     * Static: Get available limit for user
     */
    public static function getAvailableLimit(int $userId): float
    {
        $maxLimit = static::getMaxLimit($userId);
        
        // Get active loan
        $activeLoan = static::forUser($userId)
            ->active()
            ->first();
        
        if (!$activeLoan) {
            return $maxLimit;
        }
        
        // Available = max - remaining amount of active loan
        return max(0, $maxLimit - $activeLoan->remaining_amount);
    }
    
    /**
     * Static: Check if user can request new loan
     */
    public static function canRequestNewLoan(int $userId): array
    {
        // Check if has active loan
        $activeLoan = static::forUser($userId)
            ->active()
            ->first();
        
        if ($activeLoan) {
            return [
                'can_request' => false,
                'reason' => 'Anda masih memiliki pinjaman aktif yang belum lunas',
            ];
        }
        
        // Check if has pending approval
        $pendingLoan = static::forUser($userId)
            ->pending()
            ->first();
        
        if ($pendingLoan) {
            return [
                'can_request' => false,
                'reason' => 'Anda masih memiliki pengajuan pinjaman yang menunggu persetujuan',
            ];
        }
        
        return [
            'can_request' => true,
            'reason' => null,
        ];
    }
}
