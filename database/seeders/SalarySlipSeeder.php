<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SalarySlip;
use App\Models\MPresensi;

class SalarySlipSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create salary slip for user_id 7 (mobile user)
        $userId = 7;
        
        // Get user info from m_presensi
        $user = MPresensi::find($userId);
        
        if (!$user) {
            $this->command->error("User with ID {$userId} not found in m_presensi table");
            return;
        }
        
        // Delete existing slip for this user and period if exists
        SalarySlip::where('user_id', $userId)
            ->where('period', '2024-09-01')
            ->delete();
        
        // Create sample salary slip for September 2024
        SalarySlip::create([
            'user_id' => $userId,
            'period' => '2024-09-01',
            'work_days' => 18,
            
            // Employee Info
            'employee_nik' => $user->nik ?? '2408.4101',
            'employee_name' => $user->name,
            'employee_position' => 'IT INFRASTRUCTURE, NETWORK & PROGRAMMING',
            'employee_division' => 'IT SOFTWARE DEV & NETWORK',
            'bank_name' => 'BCA',
            'account_number' => 'xxxx-xxxx-1234',
            
            // Fixed Allowances
            'basic_salary' => 4500000,
            'professional_allowance' => 250000,
            'performance_allowance' => 150000,
            'position_allowance' => 0,
            
            // Variable Allowances
            'meal_allowance' => 350000,
            'transport_allowance' => 0,
            'relocation_allowance' => 0,
            'skill_allowance' => 0,
            'other_allowance' => 0,
            'incentive_10th' => 0,
            'communication_allowance' => 0,
            'incentive' => 0,
            'shift_allowance' => 0,
            'shift_count' => 0,
            'overtime_allowance' => 0,
            'overtime_hours' => 0,
            'khk_allowance' => 0,
            'khk_count' => 0,
            
            // Deductions
            'zakat' => 0,
            'tax' => 0,
            'bpjs' => 0,
            'union_fee' => 0,
            'absence_deduction' => 0,
            'absence_days' => 0,
            'cooperative' => 0,
            'bpr_installment' => 0,
            'other_deduction' => 0,
            
            // Summary
            'gross_salary' => 5250000,
            'total_deductions' => 0,
            'net_salary' => 5250000,
            'salary_in_words' => 'Lima Juta Dua Ratus Lima Puluh Ribu Rupiah',
        ]);
        
        $this->command->info("Salary slip created successfully for user_id: {$userId} ({$user->name})");
    }
}
