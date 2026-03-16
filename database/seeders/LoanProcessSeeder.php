<?php

namespace Database\Seeders;

use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\MKaryawan;
use App\Models\MPresensi;
use App\Models\SalarySlip;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Artisan;

class LoanProcessSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Setting up Loan Process Dummy Data...');

        // 1. Create or Get User for Testing
        $nik = '888888';
        $user = MPresensi::where('nik', $nik)->first();

        if (!$user) {
            // Create Karyawan in Master
            $karyawan = MKaryawan::on('pgsql_master')->create([
                'nik' => $nik,
                'nama' => 'Budi Tester Loan',
                'div_id' => 'IT',
                'dept_id' => 'DEV',
                'gaji_pokok' => 10000000, // Rp 10.000.000
                'status_karyawan' => 'Tetap',
                'tgl_masuk' => '2020-01-01',
            ]);

            // Create User in App
            $user = MPresensi::create([
                'name' => 'Budi Tester Loan',
                'email' => 'loan.tester@example.com',
                'password' => Hash::make('password'),
                'karyawan_id' => $karyawan->id,
                // 'nik' and 'kode_cabang' removed as they are not in fillable/table
                'role' => 'karyawan',
                'is_active' => true,
            ]);
            
            $this->command->info("Created User: {$user->name} (NIK: $nik)");
        } else {
            // Ensure gaji_pokok is set correctly in master
            $karyawan = MKaryawan::on('pgsql_master')->where('id', $user->karyawan_id)->first();
            if ($karyawan) {
                $karyawan->gaji_pokok = 10000000;
                $karyawan->save();
            }
            $this->command->info("Using Existing User: {$user->name} (NIK: $nik)");
        }

        // 2. Create Active Loan
        // Clear previous loans for clean state
        Loan::where('user_id', $user->id)->delete();
        
        $loanAmount = 5000000; // 5 JT
        $tenor = 5; // 5 Bulan
        $interestRate = 1; // 1%
        $interest = $loanAmount * ($interestRate/100) * $tenor;
        $totalRepayment = $loanAmount + $interest;
        $monthlyInstallment = $totalRepayment / $tenor; // 1.050.000
        
        $loan = Loan::create([
            'user_id' => $user->id,
            'amount' => $loanAmount,
            'tenor' => $tenor,
            'interest_rate' => $interestRate,
            'admin_fee' => 25000,
            'total_interest' => $interest,
            'total_repayment' => $totalRepayment,
            'monthly_installment' => $monthlyInstallment,
            'disbursement_amount' => $loanAmount - 25000,
            'remaining_amount' => $totalRepayment,
            'status' => 'active',
            'reason' => 'Tes Auto Deduction',
            'approved_by' => 1,
            'approved_at' => now()->subDays(10),
            'disbursed_at' => now()->subDays(5),
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->addMonths($tenor)->endOfMonth(),
        ]);
        
        $this->command->info("Created Active Loan ID: {$loan->id} - Installment: " . number_format($monthlyInstallment));

        // 3. Create Installments
        for ($i = 1; $i <= $tenor; $i++) {
            $dueDate = now()->addMonths($i - 1)->endOfMonth(); // Feb, Mar, Apr...
            LoanInstallment::create([
                'loan_id' => $loan->id,
                'installment_number' => $i,
                'amount' => $monthlyInstallment,
                'due_date' => $dueDate,
                'status' => 'pending',
            ]);
        }

        // 4. Create Draft Salary Slip for Current Month
        // Delete existing slip for this period
        SalarySlip::where('user_id', $user->id)
            ->where('period', now()->startOfMonth()->format('Y-m-d'))
            ->delete();

        $slip = SalarySlip::create([
            'user_id' => $user->id,
            'period' => now()->startOfMonth(),
            'work_days' => 20,
            'employee_nik' => $user->nik,
            'employee_name' => $user->name,
            'employee_position' => 'Staff IT',
            'employee_division' => 'IT',
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'basic_salary' => 10000000,
            'professional_allowance' => 0,
            'performance_allowance' => 0,
            'position_allowance' => 0,
            'meal_allowance' => 1000000,
            'transport_allowance' => 500000,
            'gross_salary' => 11500000,
            // Deductions initially 0
            'zakat' => 0,
            'tax' => 0,
            'bpjs' => 200000,
            'total_deductions' => 200000, // Static only
            'net_salary' => 11300000,
            'salary_in_words' => 'Sebelas Juta Tiga Ratus Ribu Rupiah',
        ]);
        
        $this->command->info("Created Salary Slip ID: {$slip->id} for period: {$slip->period->format('M Y')}");
        
        // 5. Run Auto-Deduction Command
        $this->command->info('Running loan:process-deductions command...');
        
        Artisan::call('loan:process-deductions', [
            'period' => now()->format('Y-m')
        ]);
        
        $output = Artisan::output();
        $this->command->line($output);
        
        // Verify
        $slip->refresh();
        $deductions = $slip->deductions()->get();
        
        $this->command->info("\n--- Verification Results ---");
        $this->command->info("Net Salary Before: Rp 11.300.000");
        $this->command->info("Net Salary After:  Rp " . number_format($slip->net_salary_after_deductions, 0, ',', '.'));
        $this->command->info("Dynamic Deductions Count: " . $deductions->count());
        
        foreach($deductions as $d) {
            $this->command->info(" > {$d->description}: Rp " . number_format($d->amount, 0, ',', '.'));
        }
        
        $this->command->info("\nMobile User Credentials:");
        $this->command->info("Email: loan.tester@example.com");
        $this->command->info("Password: password");
        $this->command->info("Loan ID: {$loan->id}");
        $this->command->info("Salary Slip ID: {$slip->id}");
    }
}
