<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalarySlip extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'period', 'work_days',
        'employee_nik', 'employee_name', 'employee_position', 'employee_division',
        'bank_name', 'account_number',
        'basic_salary', 'professional_allowance', 'performance_allowance', 'position_allowance',
        'meal_allowance', 'transport_allowance', 'relocation_allowance', 'skill_allowance',
        'other_allowance', 'incentive_10th', 'communication_allowance', 'incentive',
        'shift_allowance', 'shift_count', 'overtime_allowance', 'overtime_hours',
        'khk_allowance', 'khk_count',
        'zakat', 'tax', 'bpjs', 'union_fee', 'absence_deduction', 'absence_days',
        'cooperative', 'bpr_installment', 'other_deduction',
        'gross_salary', 'total_deductions', 'net_salary', 'salary_in_words',
        'pdf_path', 'notes',
    ];

    protected $casts = [
        'period' => 'date',
    ];

    /**
     * Calculate gross salary (total earnings)
     */
    public function calculateGrossSalary(): float
    {
        return $this->basic_salary + $this->professional_allowance + 
               $this->performance_allowance + $this->position_allowance +
               $this->meal_allowance + $this->transport_allowance +
               $this->relocation_allowance + $this->skill_allowance +
               $this->other_allowance + $this->incentive_10th +
               $this->communication_allowance + $this->incentive +
               $this->shift_allowance + $this->overtime_allowance + $this->khk_allowance;
    }

    /**
     * Calculate total deductions
     */
    public function calculateTotalDeductions(): float
    {
        return $this->zakat + $this->tax + $this->bpjs + $this->union_fee +
               $this->absence_deduction + $this->cooperative + 
               $this->bpr_installment + $this->other_deduction;
    }

    /**
     * Calculate net salary (gross - deductions)
     */
    public function calculateNetSalary(): float
    {
        return $this->calculateGrossSalary() - $this->calculateTotalDeductions();
    }
    
    /**
     * Get deductions for this salary slip
     */
    public function deductions(): HasMany
    {
        return $this->hasMany(SalaryDeduction::class);
    }
    
    /**
     * Get total deductions including dynamic deductions
     */
    public function getTotalDeductionsWithDynamicAttribute(): float
    {
        $staticDeductions = $this->calculateTotalDeductions();
        $dynamicDeductions = $this->deductions()->sum('amount');
        return $staticDeductions + $dynamicDeductions;
    }
    
    /**
     * Get net salary after all deductions
     */
    public function getNetSalaryAfterDeductionsAttribute(): float
    {
        return $this->calculateGrossSalary() - $this->total_deductions_with_dynamic;
    }
    
    /**
     * Get PDF URL attribute
     */
    public function getPdfUrlAttribute(): ?string
    {
        if (!$this->pdf_path) {
            return null;
        }
        return url('/api/v1/salaries/' . $this->id . '/export');
    }
}
